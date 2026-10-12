<?php

namespace App\Controllers;

use App\Core\Database;
use App\Services\DocumentQueryService;

class IntakeController
{
    public function index()
    {
        $officerId = $_SESSION['user_id'] ?? 0;
        $queryService = new DocumentQueryService();

        [$documents, $paginator] = $queryService->getPaginatedOfficerIntake($officerId, $_GET);
        $allPurposes = $queryService->getAllPurposes();

        require BASE_PATH . '/src/Views/officer/intake.php';
    }

    public function find()
    {
        $trackingCode = trim($_POST['tracking_code'] ?? '');
        if ($trackingCode) {
            $db = Database::getInstance();
            $doc = $db->query("SELECT id FROM documents WHERE tracking_code = :code", ['code' => $trackingCode])->fetch();
            if ($doc) {
                header('Location: /documents/' . $doc['id'] . '/manage');
                exit;
            }
        }
        
        header('Location: /intake?error=Document+not+found');
        exit;
    }
}
