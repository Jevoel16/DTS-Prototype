<?php

namespace App\Controllers;

use App\Core\Database;
use App\Core\Cache;

class DashboardController
{
    /**
     * Admin Dashboard - Shows the status distribution chart.
     */
    public function adminDashboard()
    {
        $db = Database::getInstance();
        
        // Fetch some basic stats for the dashboard
        $stats = [];
        $stats['total_documents'] = $db->query("SELECT COUNT(*) as count FROM documents")->fetch()['count'];
        $stats['pending_documents'] = $db->query("SELECT COUNT(*) as count FROM documents WHERE status = 'pending'")->fetch()['count'];
        $stats['completed_documents'] = $db->query("SELECT COUNT(*) as count FROM documents WHERE status = 'completed'")->fetch()['count'];
        
        // Fetch status distribution for the chart
        $sql = "SELECT status, COUNT(*) as count FROM documents GROUP BY status";
        $stmt = $db->query($sql);
        $results = $stmt->fetchAll();
        
        $labels = [];
        $data = [];
        $backgroundColors = [];
        
        $colorMap = [
            'pending' => '#f97316', 
            'in_transit' => '#3b82f6', 
            'processing' => '#eab308', 
            'ready_for_release' => '#84cc16', 
            'completed' => '#22c55e', 
            'declined' => '#ef4444', 
            'frozen' => '#64748b', 
        ];

        foreach ($results as $row) {
            $status = $row['status'];
            $labels[] = ucfirst(str_replace('_', ' ', $status));
            $data[] = (int)$row['count'];
            $backgroundColors[] = $colorMap[$status] ?? '#cbd5e1';
        }
        
        $chartData = json_encode([
            'labels' => $labels,
            'datasets' => [[
                'label' => 'Documents by Status',
                'data' => $data,
                'backgroundColor' => $backgroundColors
            ]]
        ]);

        require BASE_PATH . '/src/Views/admin/dashboard.php';
    }

    /**
     * Admin: View All Documents with filters
     */
    public function adminAllDocuments()
    {
        $queryService = new \App\Services\DocumentQueryService();
        
        [$documents, $paginator] = $queryService->getPaginatedAdminDocuments($_GET);
        $allPurposes = $queryService->getAllPurposes();

        require BASE_PATH . '/src/Views/admin/all-documents.php';
    }

    /**
     * Admin: View System Overview
     */
    public function adminSystemOverview()
    {
        $db = Database::getInstance();

        // Calculate some basic system metrics
        $metrics = [
            'total_users' => $db->query("SELECT COUNT(*) as count FROM users")->fetch()['count'],
            'total_departments' => $db->query("SELECT COUNT(*) as count FROM departments")->fetch()['count'],
            'total_purposes' => $db->query("SELECT COUNT(*) as count FROM purposes")->fetch()['count'],
            'total_logs' => $db->query("SELECT COUNT(*) as count FROM document_logs")->fetch()['count'],
            'failed_jobs' => $db->query("SELECT COUNT(*) as count FROM report_jobs WHERE status = 'failed'")->fetch()['count'],
        ];

        require BASE_PATH . '/src/Views/admin/system-overview.php';
    }

    /**
     * Officer Intake Dashboard - Shows recently handled documents.
     */
    public function officerIntake()
    {
        $officerId = $_SESSION['user_id'] ?? 0;
        $queryService = new \App\Services\DocumentQueryService();
        
        [$documents, $paginator] = $queryService->getPaginatedOfficerIntake($officerId, $_GET);
        $allPurposes = $queryService->getAllPurposes();

        require BASE_PATH . '/src/Views/officer/intake.php';
    }

    /**
     * Staff Tasks Dashboard - Shows documents assigned to the staff's department.
     */
    public function staffTasks()
    {
        $departmentId = $_SESSION['department_id'] ?? 0;
        $queryService = new \App\Services\DocumentQueryService();
        
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
        $queryService = new \App\Services\DocumentQueryService();
        
        [$documents, $paginator] = $queryService->getPaginatedOfficerCompletedTasks($officerId, $_GET);
        $allPurposes = $queryService->getAllPurposes();

        require BASE_PATH . '/src/Views/officer/tasks-completed.php';
    }

    /**
     * Officer Releasing Dashboard.
     */
    public function officerReleasing()
    {
        $queryService = new \App\Services\DocumentQueryService();
        
        [$documents, $paginator] = $queryService->getPaginatedOfficerReleasing($_GET);
        $allPurposes = $queryService->getAllPurposes();

        require BASE_PATH . '/src/Views/officer/releasing.php';
    }


    /**
     * Unified Statistics Dashboard for Staff and Officers.
     */
    public function statistics()
    {
        $userRole = $_SESSION['role'] ?? '';
        $viewData = [];

        if ($userRole === 'officer') {
            $departmentId = $_SESSION['department_id'] ?? 0;
            $queryService = new \App\Services\DocumentQueryService();
            
            [$documents, $paginator] = $queryService->getPaginatedStatistics($departmentId, $_GET);
            
            $viewData['releasedDocuments'] = $documents;
            $viewData['paginator'] = $paginator;
            $viewData['purposes'] = $queryService->getAllPurposes();
            $viewData['activeFilters'] = ['date', 'purpose', 'submitter', 'search'];
        }
        
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            extract($viewData);
            require BASE_PATH . '/src/Views/officer/partials/released-documents-table.php';
            exit;
        }

        extract($viewData);
        require BASE_PATH . '/src/Views/general/statistics.php';
    }

    public function getCurrentLoadData()
    {
        $period = $_GET['period'] ?? 'daily';
        $departmentId = $_SESSION['department_id'] ?? 0;
        
        $service = new \App\Services\DepartmentAnalyticsService();
        $data = $service->getMetricTimeSeries($departmentId, $period, 'received_count');

        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    public function getThroughputData()
    {
        $period = $_GET['period'] ?? 'daily';
        $departmentId = $_SESSION['department_id'] ?? 0;
        
        $service = new \App\Services\DepartmentAnalyticsService();
        $data = $service->getMetricTimeSeries($departmentId, $period, 'processed_count + released_count');

        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    public function getAverageProcessingTimeData()
    {
        $period = $_GET['period'] ?? 'daily';
        $departmentId = $_SESSION['department_id'] ?? 0;
        
        $service = new \App\Services\DepartmentAnalyticsService();
        $data = $service->getAverageProcessingTime($departmentId, $period);

        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

}
