<?php

namespace App\Controllers;

use App\Core\Database;
use App\Core\IntegrityManager;
use Dompdf\Dompdf;
use Dompdf\Options;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

class DocumentController
{
    /**
     * Display the specified document's data and logs.
     *
     * @param string $tracking_code
     */
    public function show($tracking_code)
    {
        $service = new \App\Services\DocumentQueryService();
        $document = $service->findByTrackingCode($tracking_code, 'ASC');

        if (!$document) {
            header("HTTP/1.0 404 Not Found");
            echo "Document not found.";
            exit;
        }

        if (!empty($_SERVER['HTTP_REFERER'])) {
            $refererPath = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_PATH) ?? '';
            if (!empty($refererPath) && !str_contains($refererPath, '/hash-chain') && !str_contains($refererPath, '/documents/')) {
                $_SESSION['doc_return_url'] = $_SERVER['HTTP_REFERER'];
            }
        }

        $logs = $document['logs'];

        require BASE_PATH . '/src/Views/general/show-document.php';
    }

    /**
     * Display the hash chain for a specific document.
     *
     * @param string $tracking_code
     */
    public function showHashChain($tracking_code)
    {
        $service = new \App\Services\DocumentQueryService();
        $document = $service->findByTrackingCode($tracking_code, 'ASC');

        if (!$document) {
            header("HTTP/1.0 404 Not Found");
            echo "Document not found.";
            exit;
        }

        $logs = $document['logs'];

        require BASE_PATH . '/src/Views/general/document-hash-chain.php';
    }

    /**
     * Find a document by its tracking code and redirect to the manage page.
     */
    public function find()
    {
        $trackingCode = trim($_POST['tracking_code'] ?? '');

        if (empty($trackingCode)) {
            $_SESSION['error'] = "Tracking code is required.";
            header("Location: /intake");
            exit;
        }

        $db = Database::getInstance();
        $stmt = $db->query("SELECT * FROM documents WHERE tracking_code = :tracking_code", [':tracking_code' => $trackingCode]);
        $document = $stmt->fetch();

        if (!$document) {
            $_SESSION['error'] = "Document " . htmlspecialchars($trackingCode) . " not found.";
            header("Location: /intake");
            exit;
        }

        switch ($document['status']) {
            case 'pending':
                header("Location: /documents/{$document['id']}/manage");
                exit;

            case 'processing':
            case 'in_transit':
                $_SESSION['info'] = "This document is already in process and cannot be intaked again.";
                header("Location: /intake");
                exit;

            case 'ready_for_release':
            case 'completed':
            case 'declined':
                $_SESSION['info'] = "This document has already been released, please check your tracking code again.";
                header("Location: /intake");
                exit;

            default:
                $_SESSION['error'] = "This document cannot be processed at this time. Its current status is: " . ucfirst($document['status']);
                header("Location: /intake");
                exit;
        }
    }

    /**
     * Show the form for managing a document's route (Intake).
     *
     * @param string $id
     */
    public function manage($id)
    {
        $db = Database::getInstance();
        
        $stmt = $db->query("SELECT d.*, p.name as purpose_name, p.suggested_route 
                            FROM documents d 
                            LEFT JOIN purposes p ON d.purpose_id = p.id 
                            WHERE d.id = :id", [':id' => $id]);
        $document = $stmt->fetch();

        if (!$document) {
            header("Location: /intake");
            exit;
        }

        $suggestedRoute = $document['suggested_route'] ? json_decode($document['suggested_route'], true) : [];

        $guestDept = $document['department'] ?? '';
        $purposeName = $document['purpose_name'] ?? '';
        
        // For official purposes, dynamically prepend the guest-selected unit/department
        if ($guestDept && !str_starts_with($purposeName, 'Others:')) {
            if (empty($suggestedRoute)) {
                $suggestedRoute = [['name' => $guestDept, 'is_injected' => true]];
            } else {
                // Ensure we handle legacy arrays and don't duplicate if it's already the first step
                $firstStep = is_array($suggestedRoute[0]) ? ($suggestedRoute[0]['name'] ?? '') : $suggestedRoute[0];
                if ($firstStep !== $guestDept) {
                    array_unshift($suggestedRoute, ['name' => $guestDept, 'is_injected' => true]);
                } else {
                    // It's already the first step, let's just flag it
                    $suggestedRoute[0] = ['name' => $guestDept, 'is_injected' => true];
                }
            }
        }

        $document['suggested_route'] = $suggestedRoute;

        // Fetch departments for the route building dropdown
        $stmt = $db->query("SELECT id, name FROM departments ORDER BY name ASC");
        $departments = $stmt->fetchAll();

        require BASE_PATH . '/src/Views/officer/manage-documents.php';
    }

    public function finalize($id)
    {
        $finalRouteJson = $_POST['final_route'] ?? '';
        $submittedPin = $_POST['pin'] ?? '';
        $pin = \App\Core\SecurityHelper::resolvePin($submittedPin);

        if (empty($finalRouteJson) || empty($pin)) {
            $_SESSION['error'] = "Route and Security PIN are required.";
            header("Location: /documents/{$id}/manage");
            exit;
        }

        $routeNames = json_decode($finalRouteJson, true);

        if (empty($routeNames)) {
            $_SESSION['error'] = "The route cannot be empty. Please add at least one step.";
            header("Location: /documents/{$id}/manage");
            exit;
        }

        try {
            $workflow = new \App\Services\DocumentWorkflowService();
            $officer = \App\Models\User::findById($_SESSION['user_id']);
            $workflow->finalizeIntake((int)$id, $routeNames, $officer, $pin);
            
            $doc = \App\Models\Document::findById((int)$id);
            $trackingCode = $doc ? $doc->tracking_code : '';
            $_SESSION['success'] = "Document {$trackingCode} accepted and is now in transit!";
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'Action Denied')) {
                $_SESSION['console_error'] = $e->getMessage();
            } else {
                $_SESSION['error'] = $e->getMessage();
            }
            header("Location: /documents/{$id}/manage");
            exit;
        }

        header("Location: /intake");
        exit;
    }

    public function scan()
    {
        $trackingCode = trim($_POST['tracking_code'] ?? '');
        $submittedPin = $_POST['pin'] ?? '';
        $pin = \App\Core\SecurityHelper::resolvePin($submittedPin);

        if (empty($trackingCode)) {
            $_SESSION['error'] = "Tracking code is required.";
            header("Location: " . ($_SERVER['HTTP_REFERER'] ?? '/'));
            exit;
        }

        if (empty($pin)) {
            $_SESSION['error'] = "Security PIN is required.";
            header("Location: " . ($_SERVER['HTTP_REFERER'] ?? '/'));
            exit;
        }

        try {
            $workflow = new \App\Services\DocumentWorkflowService();
            $user = \App\Models\User::findById($_SESSION['user_id']);
            $redirectRouteKey = $workflow->scanDocument($trackingCode, $user, $pin);
            
            if ($redirectRouteKey === 'releasing') {
                $_SESSION['success'] = "Document {$trackingCode} is now ready for releasing.";
                header("Location: /releasing");
            } else {
                $_SESSION['success'] = "Document {$trackingCode} has been received and added to your tasks.";
                header("Location: /tasks");
            }
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'Action Denied')) {
                $_SESSION['console_error'] = $e->getMessage();
            } else {
                $_SESSION['error'] = $e->getMessage();
            }
            header("Location: " . ($_SERVER['HTTP_REFERER'] ?? '/'));
        }
        exit;
    }

    public function decline()
    {
        $db = \App\Core\Database::getInstance();
        $documentId = $_POST['document_id'] ?? null;
        $reason = $_POST['reason'] ?? '';
        $submittedPin = $_POST['pin'] ?? '';
        $pin = \App\Core\SecurityHelper::resolvePin($submittedPin);
        
        if (!$documentId || !$reason) {
            $_SESSION['error'] = 'Document ID and decline reason are required.';
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/intake'));
            exit;
        }

        if (empty($pin)) {
            $_SESSION['error'] = "Security PIN is required.";
            header("Location: " . ($_SERVER['HTTP_REFERER'] ?? '/'));
            exit;
        }

        try {
            $workflow = new \App\Services\DocumentWorkflowService();
            $officer = \App\Models\User::findById($_SESSION['user_id']);
            
            $doc = \App\Models\Document::findById((int)$documentId);
            $trackingCode = $doc ? $doc->tracking_code : '';
            $workflow->declineDocument((int)$documentId, $reason, $officer, $pin);
            
            $_SESSION['success'] = "Document {$trackingCode} successfully declined.";
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'Action Denied')) {
                $_SESSION['console_error'] = $e->getMessage();
            } else {
                $_SESSION['error'] = $e->getMessage();
            }
        }
        
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/intake'));
        exit;
    }

    /**
     * Generate a printable PDF tracking form for the specified document.
     *
     * @param string $tracking_code
     */
    public function printTrackingForm($tracking_code)
    {
        $db = Database::getInstance();
        
        $stmt = $db->query("SELECT d.*, p.name as purpose_name 
                            FROM documents d 
                            LEFT JOIN purposes p ON d.purpose_id = p.id 
                            WHERE d.tracking_code = :tracking_code", [':tracking_code' => $tracking_code]);
        $document = $stmt->fetch();

        if (!$document) {
            header("HTTP/1.0 404 Not Found");
            echo "Document not found.";
            exit;
        }

        // Generate QR Code
        $qrOptions = new QROptions([
            'version'         => 5,
            'outputInterface' => \chillerlan\QRCode\Output\QRGdImagePNG::class,
            'eccLevel'        => \chillerlan\QRCode\Common\EccLevel::L,
            'scale'           => 5,
            'outputBase64'    => true,
        ]);
        
        $qrCode = (new QRCode($qrOptions))->render($document['tracking_code']);
        $qrCodeBase64 = preg_replace('#^data:image/[^;]+;base64,#', '', $qrCode);

        // Capture HTML view
        ob_start();
        require BASE_PATH . '/src/Views/general/tracking-form-pdf.php';
        $html = ob_get_clean();

        // Generate PDF
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $dompdf->stream('document-tracking-form-'.$document['tracking_code'].'.pdf', ["Attachment" => false]);
    }
}
