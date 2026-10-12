<?php

namespace App\Controllers;

use App\Services\DocumentQueryService;

/**
 * Task-list pages for staff and officers (/tasks, /tasks/completed, /releasing).
 * Admin dashboards live in AdminDashboardController, statistics in StatisticsController.
 */
class DashboardController
{
    /**
     * Staff Tasks Dashboard - Shows documents assigned to the staff's department.
     */
    public function staffTasks()
    {
        $departmentId = $_SESSION['department_id'] ?? 0;
        $queryService = new DocumentQueryService();

        [$documents, $paginator] = $queryService->getPaginatedStaffTasks($departmentId, $_GET);
        $allPurposes = $queryService->getAllPurposes();

        require BASE_PATH . '/src/Views/staff/tasks.php';
    }

    /**
     * Officer Completed Tasks Dashboard.
     */
    public function officerCompletedTasks()
    {
        $officerId = $_SESSION['user_id'] ?? 0;
        $queryService = new DocumentQueryService();

        [$documents, $paginator] = $queryService->getPaginatedOfficerCompletedTasks($officerId, $_GET);
        $allPurposes = $queryService->getAllPurposes();

        require BASE_PATH . '/src/Views/officer/tasks-completed.php';
    }

    /**
     * Officer Releasing Dashboard.
     */
    public function officerReleasing()
    {
        $queryService = new DocumentQueryService();

        [$documents, $paginator] = $queryService->getPaginatedOfficerReleasing($_GET);
        $allPurposes = $queryService->getAllPurposes();

        require BASE_PATH . '/src/Views/officer/releasing.php';
    }
}
