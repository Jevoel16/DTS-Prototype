<?php

namespace App\Controllers;

use App\Core\Database;
use App\Core\Cache;

class StatisticsController
{
    public function index()
    {
        $db = Database::getInstance();
        $userRole = $_SESSION['role'] ?? '';
        
        $viewData = [];

        if ($userRole === 'officer' || $userRole === 'staff') {
            $departmentId = $_SESSION['department_id'] ?? 0;
            $queryService = new \App\Services\DocumentQueryService();

            [$documents, $paginator] = $queryService->getPaginatedStatistics($departmentId, $_GET);

            $viewData['releasedDocuments'] = $documents;
            $viewData['paginator'] = $paginator;
            $viewData['purposes'] = $this->getAllPurposes();
            $viewData['activeFilters'] = ['date', 'purpose', 'submitter', 'search'];
            
            $pastReports = $db->query("SELECT * FROM report_jobs WHERE user_id = :uid AND status = 'completed' ORDER BY created_at DESC", ['uid' => $_SESSION['user_id']])->fetchAll();
            $viewData['pastReports'] = $pastReports;
        }
        
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            extract($viewData);
            require BASE_PATH . '/src/Views/officer/partials/released-documents-table.php';
            exit;
        }

        extract($viewData);
        require BASE_PATH . '/src/Views/general/statistics.php';
    }

    public function generateReport()
    {
        header('Content-Type: application/json');
        try {
            $db = Database::getInstance();
            $jobId = uniqid();
            $userId = $_SESSION['user_id'];
            $filtersJson = json_encode($_POST);
            
            $db->query("INSERT INTO report_jobs (id, user_id, status, progress, total_documents, filters, created_at, updated_at) VALUES (:id, :uid, 'queued', 0, 0, :filters, NOW(), NOW())", [
                'id' => $jobId,
                'uid' => $userId,
                'filters' => $filtersJson
            ]);

            $payload = json_encode(['class' => \App\Jobs\GenerateReportJob::class, 'data' => [$jobId, $userId, $_POST]]);
            $db->query("INSERT INTO jobs (queue, payload, attempts, available_at, created_at) VALUES ('default', :payload, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP())", [
                'payload' => $payload
            ]);

            echo json_encode(['status' => 'success', 'message' => 'Report generation started.', 'job_id' => $jobId]);
            exit;
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            exit;
        }
    }

    public function getReportStatus()
    {
        $jobId = $_GET['job_id'] ?? null;
        $db = Database::getInstance();
        $status = $db->query("SELECT * FROM report_jobs WHERE id = :id", ['id' => $jobId])->fetch();
        
        header('Content-Type: application/json');
        echo json_encode($status);
        exit;
    }

    public function cancelReport()
    {
        $jobId = $_POST['job_id'] ?? null;
        $db = Database::getInstance();
        $db->query("UPDATE report_jobs SET status = 'cancelled' WHERE id = :id AND status IN ('queued', 'processing')", ['id' => $jobId]);
        
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success']);
        exit;
    }

    public function downloadReport($jobId)
    {
        $db = Database::getInstance();
        $job = $db->query("SELECT * FROM report_jobs WHERE id = :id", ['id' => $jobId])->fetch();
        
        if (!$job || $job['status'] !== 'completed') {
            header('Location: /statistics');
            exit;
        }

        // Fallback for legacy files if file_path exists on disk
        if (!empty($job['file_path'])) {
            $legacyPath = BASE_PATH . '/storage/app/' . $job['file_path'];
            if (file_exists($legacyPath)) {
                header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
                header('Content-Disposition: attachment; filename="' . basename($legacyPath) . '"');
                readfile($legacyPath);
                exit;
            }
        }

        // Parse stored filters and job creation timestamp for point-in-time determinism
        $filters = !empty($job['filters']) ? json_decode($job['filters'], true) : [];
        $jobCreatedAt = $job['created_at'];
        $totalCount = (int) $job['total_documents'];

        $userDept = $db->query("SELECT department_id FROM users WHERE id = :uid", ['uid' => $job['user_id']])->fetch();
        $departmentId = $userDept['department_id'] ?? 0;

        // Bounded Query: upper-bounded by job creation timestamp so future released documents are excluded
        $where = ["d.status = 'completed'", "d.released_by_user_id > 0", "d.updated_at <= :job_created_at"];
        $params = [':dept_id' => $departmentId, ':job_created_at' => $jobCreatedAt];
        
        $join = "INNER JOIN users u ON d.released_by_user_id = u.id AND u.department_id = :dept_id
                 LEFT JOIN purposes p ON d.purpose_id = p.id";

        if (!empty($filters['date'])) {
            $where[] = "DATE(d.released_at) = :date";
            $params[':date'] = $filters['date'];
        }
        if (!empty($filters['search'])) {
            $where[] = "(d.tracking_code LIKE :search OR json_unquote(json_extract(d.guest_info, '$.name')) LIKE :search2)";
            $params[':search'] = '%' . $filters['search'] . '%';
            $params[':search2'] = '%' . $filters['search'] . '%';
        }
        if (!empty($filters['purpose']) && $filters['purpose'] !== 'all') {
            $where[] = "p.name = :purpose";
            $params[':purpose'] = $filters['purpose'];
        }
        if (!empty($filters['submitter'])) {
            $where[] = "LOWER(json_unquote(json_extract(d.guest_info, '$.name'))) LIKE :submitter";
            $params[':submitter'] = '%' . strtolower($filters['submitter']) . '%';
        }

        $whereSql = implode(' AND ', $where);
        $sql = "
            SELECT d.tracking_code, d.title, p.name as purpose_name, d.district, d.guest_info, d.updated_at
            FROM documents d
            {$join}
            WHERE {$whereSql}
            ORDER BY d.released_at DESC
        ";

        $stmt = $db->query($sql, $params);

        // Build descriptive filename
        $timestamp = date('Ymd_His', strtotime($jobCreatedAt));
        $docAmount = $totalCount . 'docs';

        $appliedFilters = [];
        if (!empty($filters['date'])) {
            $clean = preg_replace('/[^a-zA-Z0-9_\-]/', '', $filters['date']);
            if ($clean !== '') $appliedFilters[] = 'date_' . $clean;
        }
        if (!empty($filters['purpose']) && $filters['purpose'] !== 'all') {
            $clean = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $filters['purpose']);
            $appliedFilters[] = 'purpose_' . preg_replace('/_+/', '_', trim($clean, '_'));
        }
        if (!empty($filters['submitter'])) {
            $clean = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $filters['submitter']);
            $appliedFilters[] = 'submitter_' . preg_replace('/_+/', '_', trim($clean, '_'));
        }
        if (!empty($filters['search'])) {
            $clean = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $filters['search']);
            $appliedFilters[] = 'search_' . preg_replace('/_+/', '_', trim($clean, '_'));
        }

        $filterSuffix = !empty($appliedFilters) ? '-' . implode('-', $appliedFilters) : '';
        if (strlen($filterSuffix) > 100) $filterSuffix = substr($filterSuffix, 0, 100);

        $downloadFilename = "released-documents-{$timestamp}-{$docAmount}{$filterSuffix}.xlsx";

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $downloadFilename . '"');
        header('Cache-Control: max-age=0');

        $writer = new \OpenSpout\Writer\XLSX\Writer();
        $writer->openToFile('php://output');

        $styleCant = (new \OpenSpout\Common\Entity\Style\Style())->withFontName('Canterbury')->withFontSize(14);
        $styleBold = (new \OpenSpout\Common\Entity\Style\Style())->withFontBold(true);

        $writer->addRow(\OpenSpout\Common\Entity\Row::fromValuesWithStyle(['Republic of the Philippines'], $styleCant));
        $writer->addRow(\OpenSpout\Common\Entity\Row::fromValuesWithStyle(['Department of Education'], $styleCant));
        $writer->addRow(\OpenSpout\Common\Entity\Row::fromValuesWithStyle(['Region X - Northern Mindanao'], $styleBold));
        $writer->addRow(\OpenSpout\Common\Entity\Row::fromValuesWithStyle(['SCHOOLS DIVISION OF ILIGAN CITY'], $styleBold));
        $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([]));

        $writer->addRow(\OpenSpout\Common\Entity\Row::fromValuesWithStyle(['Tracking Code', 'Title', 'Purpose', 'District', 'Submitted By', 'Date Released'], $styleBold));

        while ($doc = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $guestName = 'N/A';
            if ($doc['guest_info']) {
                $gi = json_decode($doc['guest_info'], true);
                $guestName = $gi['name'] ?? 'N/A';
            }
            
            $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
                $doc['tracking_code'],
                $doc['title'],
                $doc['purpose_name'],
                $doc['district'],
                $guestName,
                $doc['updated_at']
            ]));
        }

        $writer->close();
        exit;
    }

    public function getReportCount()
    {
        $db = Database::getInstance();
        $count = $db->query("SELECT COUNT(*) as count FROM documents WHERE status = 'completed'")->fetch()['count'];
        header('Content-Type: application/json');
        echo json_encode(['count' => $count]);
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

    public function getPastReports()
    {
        $db = Database::getInstance();
        $userId = $_SESSION['user_id'] ?? 0;
        
        $pastReports = $db->query(
            "SELECT id, total_documents, created_at, status 
             FROM report_jobs 
             WHERE user_id = :uid AND status = 'completed' 
             ORDER BY created_at DESC 
             LIMIT 30", 
            ['uid' => $userId]
        )->fetchAll();

        header('Content-Type: application/json');
        echo json_encode($pastReports);
        exit;
    }

    private function getAllPurposes()
    {
        $db = Database::getInstance();
        $stmt = $db->query("SELECT id, name FROM purposes ORDER BY name ASC");
        return $stmt->fetchAll();
    }
}
