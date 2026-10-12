<?php

namespace App\Jobs;

use App\Core\Database;
use OpenSpout\Writer\XLSX\Writer;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Common\Entity\Row;

class GenerateReportJob
{
    protected $reportJobId;
    protected $userId;
    protected $filters;

    public function __construct(string $reportJobId, int $userId, array $filters = [])
    {
        $this->reportJobId = $reportJobId;
        $this->userId = $userId;
        $this->filters = $filters;
    }

    public function handle(): void
    {
        ini_set('memory_limit', '-1');
        set_time_limit(1200);

        $db = Database::getInstance();
        $db->query("UPDATE report_jobs SET status = 'processing', progress = 5, updated_at = NOW() WHERE id = :id", ['id' => $this->reportJobId]);

        try {
            $userDept = $db->query("SELECT department_id FROM users WHERE id = :uid", ['uid' => $this->userId])->fetch();
            $departmentId = $userDept['department_id'] ?? 0;

            // Retrieve report_job record to obtain exact created_at upper bound
            $reportJob = $db->query("SELECT created_at FROM report_jobs WHERE id = :id", ['id' => $this->reportJobId])->fetch();
            $jobCreatedAt = $reportJob['created_at'] ?? date('Y-m-d H:i:s');

            $where = ["d.status = 'completed'", "d.released_by_user_id > 0", "d.updated_at <= :job_created_at"];
            $params = [':dept_id' => $departmentId, ':job_created_at' => $jobCreatedAt];
            
            $join = "INNER JOIN users u ON d.released_by_user_id = u.id AND u.department_id = :dept_id
                     LEFT JOIN purposes p ON d.purpose_id = p.id";

            if (!empty($this->filters['date'])) {
                $where[] = "DATE(d.released_at) = :date";
                $params[':date'] = $this->filters['date'];
            }
            if (!empty($this->filters['search'])) {
                $where[] = "(d.tracking_code LIKE :search OR json_unquote(json_extract(d.guest_info, '$.name')) LIKE :search2)";
                $params[':search'] = '%' . $this->filters['search'] . '%';
                $params[':search2'] = '%' . $this->filters['search'] . '%';
            }
            if (!empty($this->filters['purpose']) && $this->filters['purpose'] !== 'all') {
                $where[] = "p.name = :purpose";
                $params[':purpose'] = $this->filters['purpose'];
            }
            if (!empty($this->filters['submitter'])) {
                $where[] = "LOWER(json_unquote(json_extract(d.guest_info, '$.name'))) LIKE :submitter";
                $params[':submitter'] = '%' . strtolower($this->filters['submitter']) . '%';
            }

            $whereSql = implode(' AND ', $where);

            // Count total matching documents up to job creation timestamp
            $countSql = "
                SELECT COUNT(*) as total
                FROM documents d
                {$join}
                WHERE {$whereSql}
            ";
            $countStmt = $db->query($countSql, $params);
            $totalCount = (int) $countStmt->fetch()['total'];

            if ($totalCount === 0) {
                throw new \Exception("No documents found for the selected filters.");
            }

            // Mark report job as completed with zero disk file footprint
            $db->query("UPDATE report_jobs SET status = 'completed', progress = 100, total_documents = :td, file_path = NULL, updated_at = NOW() WHERE id = :id", [
                'td' => $totalCount,
                'id' => $this->reportJobId
            ]);

            // Dispatch notification to user for header bell and toasts
            $notifService = new \App\Core\NotificationService();
            $notifService->notifyUser(
                $this->userId,
                'Report Ready',
                "Your generated report containing {$totalCount} document(s) is ready for download in Past Reports.",
                'success'
            );

            // Clear the user's response cache so subsequent page loads fetch fresh report listings
            $cacheFiles = glob(BASE_PATH . "/cache/responses/cache_user_{$this->userId}_*.html");
            if ($cacheFiles) {
                foreach ($cacheFiles as $f) {
                    @unlink($f);
                }
            }

        } catch (\Throwable $e) {
            $db->query("UPDATE report_jobs SET status = 'failed', error_message = :err, updated_at = NOW() WHERE id = :id", [
                'err' => $e->getMessage(),
                'id' => $this->reportJobId
            ]);

            $notifService = new \App\Core\NotificationService();
            $notifService->notifyUser(
                $this->userId,
                'Report Generation Failed',
                $e->getMessage(),
                'error'
            );
        }
    }
}
