<?php

namespace App\Controllers;

use App\Core\Database;
use App\Core\IntegrityManager;
use App\Core\Validator;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

class GuestController
{
    public function welcome()
    {
        $db = Database::getInstance();
        
        $purposes = $db->query("SELECT * FROM purposes WHERE is_official = 1 ORDER BY name")->fetchAll();
        $stmt = $db->query("SELECT id, name FROM departments ORDER BY name ASC");
        $departments = $stmt->fetchAll();

        // Pass this directly to the view instead of extracting
        $viewData = [
            'purposes' => $purposes,
            'departments' => $departments
        ]; // Pass structured data
        foreach ($purposes as &$purpose) {
            $purpose['requirements'] = $purpose['requirements'] ? json_decode($purpose['requirements'], true) : [];
        }

        ob_start();
        require __DIR__ . '/../Views/guest/welcome.php';
        $content = ob_get_clean();
        require __DIR__ . '/../Views/layouts/guest.php';
    }

    // Processes the public submission form and delegates creation to the secure workflow service.
    public function store()
    {
        $db = Database::getInstance();

        [$errors, $validated] = Validator::validate($_POST, [
            'guest_name' => 'required',
            'guest_email' => 'email',
            'guest_phone' => 'required',
            'district' => 'required',
            'department' => 'required',
            'title' => 'required',
            'purpose_id' => 'required',
            'other_purpose_text' => ''
        ]);

        if (!empty($errors)) {
            $_SESSION['error'] = implode("<br>", $errors);
            $_SESSION['field_errors'] = $errors;
            $_SESSION['old'] = $_POST;
            header("Location: /");
            exit;
        }


        try {
            $workflow = new \App\Services\DocumentWorkflowService();
            // Pass the validated data directly; the service depends on exact array keys to construct the initial state hash
            $result = $workflow->submitDocument($validated);
            
            $trackingCode = $result['tracking_code'];
            $documentId = $result['document_id'];
        } catch (\Exception $e) {
            $_SESSION['error'] = "An error occurred during submission: " . $e->getMessage();
            $_SESSION['old'] = $_POST;
            header("Location: /");
            exit;
        }

        header("Location: /success?tracking_code={$trackingCode}&document_id={$documentId}");
        exit;
    }

    // Generates a trackable QR code for the newly submitted document to facilitate physical scanning by officers.
    public function success()
    {
        $tracking_code = trim($_GET['tracking_code'] ?? '');
        if (!$tracking_code) {
            header('Location: /');
            exit;
        }

        $options = new QROptions([
            'version'    => 5,
            'outputType' => \chillerlan\QRCode\Output\QRGdImagePNG::class,
            'eccLevel'   => \chillerlan\QRCode\Common\EccLevel::L,
            'scale'      => 5,
        ]);
        
        $qrcode = new QRCode($options);
        $qrCodeImage = (string) $qrcode->render($tracking_code);

        ob_start();
        require __DIR__ . '/../Views/guest/success.php';
        $content = ob_get_clean();
        require __DIR__ . '/../Views/layouts/guest.php';
    }

    // Aggregates tracking data and full audit logs for one or more documents requested by a guest.
    public function track()
    {
        $codesParam = $_GET['codes'] ?? '';
        $trackingCodes = array_filter(array_map('trim', explode(',', $codesParam)));

        if (empty($trackingCodes)) {
            $_SESSION['info'] = "Please enter a tracking code to view its status.";
            header("Location: /");
            exit;
        }

        $service = new \App\Services\DocumentQueryService();
        $documents = $service->getMultipleWithLogs($trackingCodes);

        ob_start();
        require __DIR__ . '/../Views/guest/track.php';
        $content = ob_get_clean();
        require __DIR__ . '/../Views/layouts/guest.php';
    }

    public function getTrackedDocumentModule($tracking_code)
    {
        $tracking_code = trim($tracking_code);
        $service = new \App\Services\DocumentQueryService();
        $document = $service->findByTrackingCode($tracking_code, 'DESC');

        if (!$document) {
            http_response_code(404);
            echo "Not Found";
            return;
        }

        ob_start();
        require __DIR__ . '/../Views/guest/partials/document-card.php';
        echo ob_get_clean();
    }

    public function getStatusUpdates()
    {
        $codesParam = $_GET['codes'] ?? '';
        $trackingCodes = array_filter(array_map('trim', explode(',', $codesParam)));

        if (empty($trackingCodes)) {
            header('Content-Type: application/json');
            echo json_encode([]);
            return;
        }

        $db = Database::getInstance();
        $placeholders = implode(',', array_fill(0, count($trackingCodes), '?'));
        
        $sql = "SELECT tracking_code, status, current_step FROM documents WHERE tracking_code IN ($placeholders)";
        $stmt = $db->getConnection()->prepare($sql);
        $stmt->execute(array_values($trackingCodes));
        $statuses = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        header('Content-Type: application/json');
        echo json_encode($statuses);
    }
}
