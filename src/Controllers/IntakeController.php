<?php

namespace App\Controllers;

use App\Core\Database;
use App\Core\Cache;

class IntakeController
{
    public function index()
    {
        $officerId = $_SESSION['user_id'] ?? 0;
        $db = Database::getInstance();
        
        
        $filters = $this->buildFilterQuery(['date', 'status', 'purpose', 'submitter', 'search'], 'max_logs.created_at');
        $params = array_merge([':officer_id' => $officerId], $filters['params']);
        
        $cursor = $_GET['cursor'] ?? null;

        $cacheKey = 'count_intake_controller_' . md5(json_encode(array_merge($params, $filters)));
        $totalItems = Cache::remember($cacheKey, 300, function() use ($db, $params, $filters) {
            $countSql = "WITH MaxLogs AS (
                             SELECT document_id, MAX(created_at) as created_at
                             FROM document_logs
                             WHERE user_id = :officer_id 
                               AND action_category = 1
                               AND created_at >= DATE_SUB(NOW(), INTERVAL 4 WEEK)
                             GROUP BY document_id
                         )
                         SELECT COUNT(d.id) as total 
                         FROM MaxLogs max_logs
                         INNER JOIN documents d ON d.id = max_logs.document_id 
                         LEFT JOIN purposes p ON d.purpose_id = p.id
                         WHERE 1=1 " . $filters['sql'];
            $countStmt = $db->query($countSql, $params);
            return $countStmt->fetch()['total'] ?? 0;
        });

        if ($cursor) {
            $parts = explode('_', $cursor);
            if (count($parts) == 2) {
                $filters['sql'] .= " AND (max_logs.created_at < :c_time1 OR (max_logs.created_at = :c_time2 AND max_logs.document_id < :c_id))";
                $params[':c_time1'] = $parts[0];
                $params[':c_time2'] = $parts[0];
                $params[':c_id'] = $parts[1];
            }
        }

        $perPage = 15;
        $limit = $perPage + 1;

        $sql = "WITH MaxLogs AS (
                    SELECT document_id, MAX(created_at) as created_at
                    FROM document_logs
                    WHERE user_id = :officer_id 
                      AND action_category = 1
                      AND created_at >= DATE_SUB(NOW(), INTERVAL 4 WEEK)
                    GROUP BY document_id
                )
                SELECT d.id, d.tracking_code, d.title, d.status, d.created_at, d.guest_info, p.name as purpose_name, max_logs.created_at as handled_at 
                FROM MaxLogs max_logs
                INNER JOIN documents d ON d.id = max_logs.document_id 
                LEFT JOIN purposes p ON d.purpose_id = p.id 
                WHERE 1=1 " . $filters['sql'] . "
                ORDER BY max_logs.created_at DESC, max_logs.document_id DESC
                LIMIT {$limit}";
                
        $stmt = $db->query($sql, $params);
        $documents = $stmt->fetchAll();
        
        $nextCursor = null;
        if (count($documents) > $perPage) {
            $nextCursor = $documents[$perPage - 1]['handled_at'] . '_' . $documents[$perPage - 1]['id'];
        }
        
        $paginator = new \App\Utils\CursorPaginator($documents, $perPage, $nextCursor, $totalItems, '?' . http_build_query(array_diff_key($_GET, ['cursor' => ''])));
        $documents = $paginator->getItems();
        $allPurposes = $this->getAllPurposes();

        foreach ($documents as &$doc) {
            $doc['guest_name'] = 'N/A';
            if (!empty($doc['guest_info'])) {
                $guestInfo = json_decode($doc['guest_info'], true);
                if (isset($guestInfo['name'])) {
                    $doc['guest_name'] = $guestInfo['name'];
                }
            }
        }

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

    private function buildFilterQuery(array $allowedFilters, $dateColumn = 'd.created_at')
    {
        $where = [];
        $params = [];

        if (in_array('date', $allowedFilters) && !empty($_GET['date'])) {
            $where[] = "{$dateColumn} >= :filter_date_start AND {$dateColumn} <= :filter_date_end";
            $params[':filter_date_start'] = $_GET['date'] . ' 00:00:00';
            $params[':filter_date_end'] = $_GET['date'] . ' 23:59:59';
        }

        if (in_array('status', $allowedFilters) && !empty($_GET['status']) && $_GET['status'] !== 'all') {
            $where[] = "d.status = :filter_status";
            $params[':filter_status'] = $_GET['status'];
        }

        if (in_array('purpose', $allowedFilters) && !empty($_GET['purpose']) && $_GET['purpose'] !== 'all') {
            $where[] = "p.name = :filter_purpose";
            $params[':filter_purpose'] = $_GET['purpose'];
        }

        if (in_array('submitter', $allowedFilters) && !empty($_GET['submitter'])) {
            $where[] = "d.guest_info LIKE :filter_submitter";
            $params[':filter_submitter'] = '%"name":"%' . trim($_GET['submitter']) . '%"%';
        }

        if (in_array('search', $allowedFilters) && !empty($_GET['search'])) {
            $searchTerm = trim($_GET['search']);
            if (preg_match('/^DEPED-/i', $searchTerm)) {
                $where[] = "d.tracking_code LIKE :filter_search1";
                $params[':filter_search1'] = $searchTerm . '%';
            } else {
                $where[] = "(d.tracking_code LIKE :filter_search1 OR d.title LIKE :filter_search2)";
                $params[':filter_search1'] = '%' . $searchTerm . '%';
                $params[':filter_search2'] = '%' . $searchTerm . '%';
            }
        }

        return [
            'sql' => empty($where) ? '' : ' AND ' . implode(' AND ', $where),
            'params' => $params
        ];
    }

    private function getAllPurposes()
    {
        $db = Database::getInstance();
        $stmt = $db->query("SELECT id, name FROM purposes ORDER BY name ASC");
        return $stmt->fetchAll();
    }
}
