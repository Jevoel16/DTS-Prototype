<?php

namespace App\Middleware;

use App\Models\User;
use App\Models\Document;
use App\Policies\DocumentPolicy;

class DocumentPolicyMiddleware
{
    /**
     * Handle document policy authorization checks as route middleware.
     *
     * @param string $ability The policy method to check (e.g. 'view', 'process', 'manage')
     */
    public function handle(string $ability = 'view')
    {
        if (!isset($_SESSION['user_id'])) {
            header("Location: /login");
            exit;
        }

        $user = User::findById((int)$_SESSION['user_id']);
        if (!$user) {
            header("Location: /login");
            exit;
        }

        // Grab document ID or tracking code from URL or POST body
        $documentId = $_POST['document_id'] ?? $_GET['document_id'] ?? null;
        $trackingCode = $_POST['tracking_code'] ?? $_GET['tracking_code'] ?? null;

        $document = null;
        if ($documentId) {
            $docModel = Document::findById((int)$documentId);
            $document = $docModel ? (array)$docModel : null;
        } elseif ($trackingCode) {
            $docModel = Document::findByTrackingCode((string)$trackingCode);
            $document = $docModel ? (array)$docModel : null;
        }

        if (!$document) {
            // If no target document specified in request, let controller handle parameter missing
            return;
        }

        $policy = new DocumentPolicy();

        if (method_exists($policy, $ability)) {
            $authorized = $policy->$ability($user, $document);
            if (!$authorized) {
                $_SESSION['error'] = "Access denied: You do not have authorization or the document integrity check failed.";
                header("Location: " . ($_SERVER['HTTP_REFERER'] ?? '/dashboard'));
                exit;
            }
        }
    }
}
