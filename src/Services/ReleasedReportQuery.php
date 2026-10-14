<?php

namespace App\Services;

/**
 * Shared filter logic for the "released documents" XLSX report.
 * Used by GenerateReportJob (to count) and StatisticsController::downloadReport
 * (to stream rows), so both always agree on which documents are in a report.
 */
class ReleasedReportQuery
{
    /**
     * Keep only the supported filter fields, as trimmed strings; drop a malformed date.
     *
     * @return array<string, string>
     */
    public static function sanitizeFilters(array $input): array
    {
        $filters = [];
        foreach (['date', 'purpose', 'submitter', 'search'] as $key) {
            $value = $input[$key] ?? '';
            if (is_string($value) && trim($value) !== '') {
                $filters[$key] = mb_substr(trim($value), 0, 190);
            }
        }
        if (isset($filters['date']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['date'])) {
            unset($filters['date']);
        }
        return $filters;
    }

    /**
     * @return array{from: string, where: string, params: array}
     */
    public static function build(int $departmentId, string $jobCreatedAt, array $filters): array
    {
        // Upper-bounded by the job's creation time so documents released later are excluded
        $where = ["d.status = 'completed'", "d.released_by_user_id > 0", "d.updated_at <= :job_created_at"];
        $params = [':dept_id' => $departmentId, ':job_created_at' => $jobCreatedAt];

        if (!empty($filters['date'])) {
            // Range instead of DATE(d.released_at) so the released_at index can be used
            $where[] = "d.released_at >= :date_start AND d.released_at < :date_end";
            $params[':date_start'] = $filters['date'] . ' 00:00:00';
            $params[':date_end'] = date('Y-m-d', strtotime($filters['date'] . ' +1 day')) . ' 00:00:00';
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

        return [
            'from' => "documents d
                       INNER JOIN users u ON d.released_by_user_id = u.id AND u.department_id = :dept_id
                       LEFT JOIN purposes p ON d.purpose_id = p.id",
            'where' => implode(' AND ', $where),
            'params' => $params,
        ];
    }
}
