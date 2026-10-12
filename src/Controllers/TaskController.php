<?php

namespace App\Controllers;

use App\Core\Database;
use App\Core\IntegrityManager;

class TaskController
{
    /**
     * Mark the current step for a document as complete and advance it.
     *
     * @param string $id
     */
    public function complete($id)
    {
        $db = Database::getInstance();
        $submittedPin = $_POST['pin'] ?? '';
        $pin = \App\Core\SecurityHelper::resolvePin($submittedPin);

        if (empty($pin)) {
            $_SESSION['error'] = "Security PIN is required.";
            header("Location: " . ($_SERVER['HTTP_REFERER'] ?? '/tasks'));
            exit;
        }

        $currentUser = \App\Models\User::findById($_SESSION['user_id'] ?? 0);

        try {
            $workflow = new \App\Services\DocumentWorkflowService();
            $workflow->completeTask((int)$id, '', $currentUser, $pin);
            
            $doc = \App\Models\Document::findById((int)$id);
            $trackingCode = $doc ? $doc->tracking_code : 'Unknown';
            $_SESSION['success'] = "Document $trackingCode is now in transit.";
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'Action Denied')) {
                $_SESSION['console_error'] = $e->getMessage();
            } else {
                $_SESSION['error'] = $e->getMessage();
            }
        }
        
        header("Location: /tasks");
        exit;
    }
}
