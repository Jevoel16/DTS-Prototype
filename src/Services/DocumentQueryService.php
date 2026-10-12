<?php
namespace App\Services;

use App\Core\Database;
use App\Core\Cache;

class DocumentQueryService
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findByTrackingCode(string $trackingCode, string $logOrder = 'DESC'): ?array
    {
        $stmt = $this->db->query("SELECT d.*, p.name as purpose_name, p.suggested_route 
                                  FROM documents d 
                                  LEFT JOIN purposes p ON d.purpose_id = p.id 
                                  WHERE d.tracking_code = :tracking_code", [':tracking_code' => $trackingCode]);
        $document = $stmt->fetch();
        
        if ($document) {
            $document['logs'] = $this->getLogsForDocumentOrdered($document['id'], $logOrder);
            $document['suggested_route'] = $document['suggested_route'] ? json_decode($document['suggested_route'], true) : [];
        }
        
        return $document ?: null;
    }

    public function getLogsForDocumentOrdered(int $documentId, string $order = 'DESC'): array
    {
        $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';
        $stmt = $this->db->query("SELECT l.*, u.name as user_name 
                                  FROM document_logs l 
                                  LEFT JOIN users u ON l.user_id = u.id 
                                  WHERE l.document_id = :doc_id 
                                  ORDER BY l.created_at $order", [':doc_id' => $documentId]);
        return $stmt->fetchAll();
    }

    public function getMultipleWithLogs(array $trackingCodes): array
    {
        if (empty($trackingCodes)) return [];

        $placeholders = implode(',', array_fill(0, count($trackingCodes), '?'));
        
        $sql = "SELECT d.*, p.name as purpose_name, p.suggested_route 
                FROM documents d 
                LEFT JOIN purposes p ON d.purpose_id = p.id 
                WHERE d.tracking_code IN ($placeholders)";
        
        $stmt = $this->db->getConnection()->prepare($sql);
        $stmt->execute(array_values($trackingCodes));
        $documents = $stmt->fetchAll();
        
        if (empty($documents)) return [];

        // Optimize N+1 by fetching all logs at once
        $docIds = array_column($documents, 'id');
        $idPlaceholders = implode(',', array_fill(0, count($docIds), '?'));
        
        $logSql = "SELECT l.*, u.name as user_name 
                   FROM document_logs l 
                   LEFT JOIN users u ON l.user_id = u.id 
                   WHERE l.document_id IN ($idPlaceholders) 
                   ORDER BY l.created_at DESC";
        $logStmt = $this->db->getConnection()->prepare($logSql);
        $logStmt->execute(array_values($docIds));
        $allLogs = $logStmt->fetchAll();

        // Group logs by document_id
        $logsByDoc = [];
        foreach ($allLogs as $log) {
            $logsByDoc[$log['document_id']][] = $log;
        }

        foreach ($documents as &$doc) {
            $doc['logs'] = $logsByDoc[$doc['id']] ?? [];
            $doc['suggested_route'] = $doc['suggested_route'] ? json_decode($doc['suggested_route'], true) : [];
        }

        return $documents;
    }
    public function normalizeGuestInfo(array &$documents): void
    {
        foreach ($documents as &$doc) {
            $doc['guest_name'] = 'N/A';
            if (!empty($doc['guest_info'])) {
                $guestInfo = is_string($doc['guest_info']) ? json_decode($doc['guest_info'], true) : $doc['guest_info'];
                if (isset($guestInfo['name'])) {
                    $doc['guest_name'] = $guestInfo['name'];
                }
            }
        }
    }

    public function getAllPurposes(): array
    {
        $stmt = $this->db->query("SELECT id, name FROM purposes ORDER BY name ASC");
        return $stmt->fetchAll();
    }

    public function buildFilterQuery(array $allowedFilters, string $dateColumn, array $requestParams): array
    {
        $where = [];
        $params = [];

        if (in_array('date', $allowedFilters) && !empty($requestParams['date'])) {
            $where[] = "{$dateColumn} >= :filter_date_start AND {$dateColumn} <= :filter_date_end";
            $params[':filter_date_start'] = $requestParams['date'] . ' 00:00:00';
            $params[':filter_date_end'] = $requestParams['date'] . ' 23:59:59';
        }

        if (in_array('status', $allowedFilters) && !empty($requestParams['status']) && $requestParams['status'] !== 'all') {
            $where[] = "d.status = :filter_status";
            $params[':filter_status'] = $requestParams['status'];
        }

        if (in_array('purpose', $allowedFilters) && !empty($requestParams['purpose']) && $requestParams['purpose'] !== 'all') {
            $where[] = "p.name = :filter_purpose";
            $params[':filter_purpose'] = $requestParams['purpose'];
        }

        if (in_array('submitter', $allowedFilters) && !empty($requestParams['submitter'])) {
            $where[] = "d.guest_info LIKE :filter_submitter";
            $params[':filter_submitter'] = '%"name":"%' . trim($requestParams['submitter']) . '%"%';
        }

        if (in_array('search', $allowedFilters) && !empty($requestParams['search'])) {
            $searchTerm = trim($requestParams['search']);
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

    public function getPaginatedAdminDocuments(array $requestParams, int $perPage = 15): array
    {
        $filters = $this->buildFilterQuery(['date', 'status', 'purpose', 'submitter', 'search'], 'd.created_at', $requestParams);
        
        $cursor = $requestParams['cursor'] ?? null;
        
        $cacheKey = 'count_docs_' . md5(json_encode($filters));
        $totalItems = Cache::remember($cacheKey, 300, function() use ($filters) {
            $countSql = "SELECT COUNT(*) as total FROM documents d LEFT JOIN purposes p ON d.purpose_id = p.id WHERE 1=1" . $filters['sql'];
            $countStmt = $this->db->query($countSql, $filters['params']);
            return $countStmt->fetch()['total'] ?? 0;
        });
        
        if ($cursor) {
            $parts = explode('_', $cursor);
            if (count($parts) === 2) {
                $filters['sql'] .= " AND (d.created_at < :c_time1 OR (d.created_at = :c_time2 AND d.id < :c_id))";
                $filters['params'][':c_time1'] = $parts[0];
                $filters['params'][':c_time2'] = $parts[0];
                $filters['params'][':c_id'] = (int)$parts[1];
            } else {
                // Fallback for legacy single ID cursor
                $filters['sql'] .= " AND d.id < :cursor";
                $filters['params'][':cursor'] = $cursor;
            }
        }

        $limit = $perPage + 1;
        
        $sql = "SELECT d.id, d.tracking_code, d.title, d.status, d.created_at, d.guest_info, p.name as purpose_name 
                FROM documents d 
                LEFT JOIN purposes p ON d.purpose_id = p.id 
                WHERE 1=1" . $filters['sql'] . " 
                ORDER BY d.created_at DESC, d.id DESC
                LIMIT {$limit}";
                
        $stmt = $this->db->query($sql, $filters['params']);
        $documents = $stmt->fetchAll();
        
        $nextCursor = null;
        if (count($documents) > $perPage) {
            $nextCursor = $documents[$perPage - 1]['created_at'] . '_' . $documents[$perPage - 1]['id'];
        }
        
        $paginator = new \App\Utils\CursorPaginator($documents, $perPage, $nextCursor, $totalItems, '?' . http_build_query(array_diff_key($requestParams, ['cursor' => ''])));
        $documents = $paginator->getItems();
        
        $this->normalizeGuestInfo($documents);
        
        return [$documents, $paginator];
    }

    public function getPaginatedOfficerIntake(int $officerId, array $requestParams, int $perPage = 15): array
    {
        $filters = $this->buildFilterQuery(['date', 'status', 'purpose', 'submitter', 'search'], 'max_logs.handled_at', $requestParams);
        $params = array_merge([':officer_id' => $officerId], $filters['params']);
        
        $cursor = $requestParams['cursor'] ?? null;
        
        $cacheKey = 'count_intake_' . md5(json_encode(array_merge($params, $filters)));
        $totalItems = Cache::remember($cacheKey, 300, function() use ($params, $filters) {
            $countSql = "WITH MaxLogs AS (
                             SELECT document_id, MAX(created_at) as created_at
                             FROM document_logs
                             WHERE user_id = :officer_id AND action_category = 1
                             GROUP BY document_id
                         )
                         SELECT COUNT(d.id) as total 
                         FROM MaxLogs max_logs
                         INNER JOIN documents d ON d.id = max_logs.document_id 
                         LEFT JOIN purposes p ON d.purpose_id = p.id
                         WHERE 1=1 " . $filters['sql'];
            $countStmt = $this->db->query($countSql, $params);
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

        $limit = $perPage + 1;

        $sql = "WITH MaxLogs AS (
                    SELECT document_id, MAX(created_at) as created_at
                    FROM document_logs
                    WHERE user_id = :officer_id AND action_category = 1
                    GROUP BY document_id
                )
                SELECT d.id, d.tracking_code, d.title, d.status, d.created_at, d.guest_info, p.name as purpose_name, max_logs.created_at as handled_at 
                FROM MaxLogs max_logs
                INNER JOIN documents d ON d.id = max_logs.document_id 
                LEFT JOIN purposes p ON d.purpose_id = p.id 
                WHERE 1=1 " . $filters['sql'] . "
                ORDER BY max_logs.created_at DESC, max_logs.document_id DESC
                LIMIT {$limit}";
                
        $stmt = $this->db->query($sql, $params);
        $documents = $stmt->fetchAll();
        
        $nextCursor = null;
        if (count($documents) > $perPage) {
            $nextCursor = $documents[$perPage - 1]['handled_at'] . '_' . $documents[$perPage - 1]['id'];
        }
        
        $paginator = new \App\Utils\CursorPaginator($documents, $perPage, $nextCursor, $totalItems, '?' . http_build_query(array_diff_key($requestParams, ['cursor' => ''])));
        $documents = $paginator->getItems();
        
        $this->normalizeGuestInfo($documents);

        return [$documents, $paginator];
    }

    public function getPaginatedStaffTasks(int $departmentId, array $requestParams, int $perPage = 15): array
    {
        $filters = $this->buildFilterQuery(['date', 'status', 'purpose', 'submitter', 'search'], 'd.created_at', $requestParams);
        $params = array_merge([':dept_id' => $departmentId], $filters['params']);
        
        $cacheKey = 'count_staff_' . md5(json_encode(array_merge($params, $filters)));
        // Exclude both 'in_transit' (documents moving between departments) and 'ready_for_release' (documents awaiting final dispatch at releasing table)
        // to ensure tasks table only contains active departmental work items.
        $totalItems = Cache::remember($cacheKey, 300, function() use ($params, $filters) {
            $countSql = "SELECT COUNT(*) as total FROM documents d LEFT JOIN purposes p ON d.purpose_id = p.id WHERE d.current_department_id = :dept_id AND d.status NOT IN ('in_transit', 'ready_for_release')" . $filters['sql'];
            $countStmt = $this->db->query($countSql, $params);
            return $countStmt->fetch()['total'] ?? 0;
        });

        $cursor = $requestParams['cursor'] ?? null;
        if ($cursor) {
            $filters['sql'] .= " AND d.id > :cursor";
            $params[':cursor'] = $cursor;
        }

        $limit = $perPage + 1;
        
        $sql = "SELECT d.id, d.tracking_code, d.title, d.status, d.created_at, p.name as purpose_name 
                FROM documents d 
                LEFT JOIN purposes p ON d.purpose_id = p.id 
                WHERE d.current_department_id = :dept_id AND d.status NOT IN ('in_transit', 'ready_for_release')" . $filters['sql'] . " 
                ORDER BY d.id ASC
                LIMIT {$limit}";
                
        $stmt = $this->db->query($sql, $params);
        $documents = $stmt->fetchAll();
        
        $nextCursor = null;
        if (count($documents) > $perPage) {
            $nextCursor = $documents[$perPage - 1]['id'];
        }
        
        $paginator = new \App\Utils\CursorPaginator($documents, $perPage, $nextCursor, $totalItems, '?' . http_build_query(array_diff_key($requestParams, ['cursor' => ''])));
        $documents = $paginator->getItems();
        
        $this->normalizeGuestInfo($documents);

        return [$documents, $paginator];
    }

    public function getPaginatedOfficerCompletedTasks(int $officerId, array $requestParams, int $perPage = 15): array
    {
        $filters = $this->buildFilterQuery(['date', 'status', 'purpose', 'submitter', 'search'], 'max_logs.handled_at', $requestParams);
        $params = array_merge([':officer_id' => $officerId], $filters['params']);
        
        $cursor = $requestParams['cursor'] ?? null;
        
        $cacheKey = 'count_completed_' . md5(json_encode(array_merge($params, $filters)));
        $totalItems = Cache::remember($cacheKey, 300, function() use ($params, $filters) {
            $countSql = "WITH MaxLogs AS (
                             SELECT document_id, MAX(created_at) as created_at
                             FROM document_logs
                             WHERE user_id = :officer_id AND action_category = 2
                             GROUP BY document_id
                         )
                         SELECT COUNT(d.id) as total 
                         FROM MaxLogs max_logs
                         INNER JOIN documents d ON d.id = max_logs.document_id 
                         LEFT JOIN purposes p ON d.purpose_id = p.id
                         WHERE 1=1 " . $filters['sql'];
            $countStmt = $this->db->query($countSql, $params);
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

        $limit = $perPage + 1;
        
        $sql = "WITH MaxLogs AS (
                    SELECT document_id, MAX(created_at) as created_at
                    FROM document_logs
                    WHERE user_id = :officer_id AND action_category = 2
                    GROUP BY document_id
                )
                SELECT d.id, d.tracking_code, d.title, d.status, d.created_at, d.guest_info, p.name as purpose_name, max_logs.created_at as handled_at 
                FROM MaxLogs max_logs 
                INNER JOIN documents d ON d.id = max_logs.document_id 
                LEFT JOIN purposes p ON d.purpose_id = p.id 
                WHERE 1=1 " . $filters['sql'] . "
                ORDER BY max_logs.created_at DESC, max_logs.document_id DESC
                LIMIT {$limit}";
                
        $stmt = $this->db->query($sql, $params);
        $documents = $stmt->fetchAll();
        
        $nextCursor = null;
        if (count($documents) > $perPage) {
            $nextCursor = $documents[$perPage - 1]['handled_at'] . '_' . $documents[$perPage - 1]['id'];
        }
        
        $paginator = new \App\Utils\CursorPaginator($documents, $perPage, $nextCursor, $totalItems, '?' . http_build_query(array_diff_key($requestParams, ['cursor' => ''])));
        $documents = $paginator->getItems();

        $this->normalizeGuestInfo($documents);

        return [$documents, $paginator];
    }

    public function getPaginatedOfficerReleasing(array $requestParams, int $perPage = 15): array
    {
        $filters = $this->buildFilterQuery(['date', 'purpose', 'submitter', 'search'], 'd.created_at', $requestParams);
        
        $cursor = $requestParams['cursor'] ?? null;
        
        $cacheKey = 'count_releasing_' . md5(json_encode($filters));
        $totalItems = Cache::remember($cacheKey, 300, function() use ($filters) {
            $countSql = "SELECT COUNT(*) as total FROM documents d LEFT JOIN purposes p ON d.purpose_id = p.id WHERE d.status = 'ready_for_release'" . $filters['sql'];
            $countStmt = $this->db->query($countSql, $filters['params']);
            return $countStmt->fetch()['total'] ?? 0;
        });
        
        if ($cursor) {
            $filters['sql'] .= " AND d.id < :cursor";
            $filters['params'][':cursor'] = $cursor;
        }

        $limit = $perPage + 1;
        
        $sql = "SELECT d.id, d.tracking_code, d.title, d.status, d.created_at, d.guest_info, p.name as purpose_name 
                FROM documents d 
                LEFT JOIN purposes p ON d.purpose_id = p.id 
                WHERE d.status = 'ready_for_release'" . $filters['sql'] . " 
                ORDER BY d.id DESC
                LIMIT {$limit}";
                
        $stmt = $this->db->query($sql, $filters['params']);
        $documents = $stmt->fetchAll();
        
        $nextCursor = null;
        if (count($documents) > $perPage) {
            $nextCursor = $documents[$perPage - 1]['id'];
        }
        
        $paginator = new \App\Utils\CursorPaginator($documents, $perPage, $nextCursor, $totalItems, '?' . http_build_query(array_diff_key($requestParams, ['cursor' => ''])));
        $documents = $paginator->getItems();

        $this->normalizeGuestInfo($documents);

        return [$documents, $paginator];
    }

    public function getPaginatedStatistics(int $departmentId, array $requestParams, int $perPage = 10): array
    {
        $filterPurpose = $requestParams['purpose'] ?? null;
        $filterSubmitter = $requestParams['submitter'] ?? null;
        $searchTerm = $requestParams['search'] ?? null;
        $filterDate = $requestParams['date'] ?? null;

        $where = ["d.status = 'completed'", "d.released_by_user_id > 0"];
        $params = [];

        // Join users to check department_id and purposes for filtering
        $join = "INNER JOIN users u ON d.released_by_user_id = u.id AND u.department_id = :dept_id
                 LEFT JOIN purposes p ON d.purpose_id = p.id";
        $params[':dept_id'] = $departmentId;

        if ($filterDate) {
            $where[] = "d.released_at >= :date_start AND d.released_at <= :date_end";
            $params[':date_start'] = $filterDate . ' 00:00:00';
            $params[':date_end'] = $filterDate . ' 23:59:59';
        }
        if ($searchTerm) {
            $where[] = "(d.tracking_code LIKE :search OR json_unquote(json_extract(d.guest_info, '$.name')) LIKE :search2)";
            $params[':search'] = '%' . $searchTerm . '%';
            $params[':search2'] = '%' . $searchTerm . '%';
        }
        if ($filterPurpose && $filterPurpose !== 'all') {
            $where[] = "p.name = :purpose";
            $params[':purpose'] = $filterPurpose;
        }
        if ($filterSubmitter) {
            $where[] = "LOWER(json_unquote(json_extract(d.guest_info, '$.name'))) LIKE :submitter";
            $params[':submitter'] = '%' . strtolower($filterSubmitter) . '%';
        }

        $whereSql = implode(' AND ', $where);

        $cursor = $requestParams['cursor'] ?? null;
        
        $cacheKey = 'count_stats_' . md5(json_encode($params) . $whereSql);
        $totalItems = Cache::remember($cacheKey, 300, function() use ($join, $whereSql, $params) {
            $countSql = "SELECT COUNT(*) as total FROM documents d {$join} WHERE {$whereSql}";
            $countStmt = $this->db->query($countSql, $params);
            return $countStmt->fetch()['total'] ?? 0;
        });

        if ($cursor) {
            $parts = explode('_', $cursor);
            if (count($parts) == 2) {
                $whereSql .= " AND (d.released_at < :c_time1 OR (d.released_at = :c_time2 AND d.id < :c_id))";
                $params[':c_time1'] = $parts[0];
                $params[':c_time2'] = $parts[0];
                $params[':c_id'] = $parts[1];
            }
        }

        $limit = $perPage + 1;

        $sql = "SELECT d.*, p.name as purpose_name 
                FROM documents d 
                {$join} 
                WHERE {$whereSql} 
                ORDER BY d.released_at DESC, d.id DESC 
                LIMIT {$limit}";
        
        $stmt = $this->db->query($sql, $params);
        $documents = $stmt->fetchAll();
        
        $nextCursor = null;
        if (count($documents) > $perPage) {
            $nextCursor = $documents[$perPage - 1]['released_at'] . '_' . $documents[$perPage - 1]['id'];
        }
        
        $paginator = new \App\Utils\CursorPaginator($documents, $perPage, $nextCursor, $totalItems, '?' . http_build_query(array_diff_key($requestParams, ['cursor' => ''])));
        $documents = $paginator->getItems();
        
        foreach ($documents as &$doc) {
            $doc['guest_info'] = $doc['guest_info'] ? (is_string($doc['guest_info']) ? json_decode($doc['guest_info'], true) : $doc['guest_info']) : [];
        }

        return [$documents, $paginator];
    }
}
