<?php

namespace App\Services;

use App\Core\Database;

class AdminAnalyticsService
{
    private function getDateParameters($period)
    {
        $endDate = date('Y-m-d H:i:s');
        switch ($period) {
            case 'yearly':
                $startDate = date('Y-m-d H:i:s', strtotime('-5 years'));
                $dateFormat = '%Y'; // MySQL format
                break;
            case 'monthly':
                $startDate = date('Y-m-d H:i:s', strtotime('-12 months'));
                $dateFormat = '%Y-%m';
                break;
            case 'weekly':
                $startDate = date('Y-m-d H:i:s', strtotime('-12 weeks'));
                $dateFormat = '%x-%v'; // Year-week
                break;
            case 'daily':
            default:
                $startDate = date('Y-m-d H:i:s', strtotime('-30 days'));
                $dateFormat = '%Y-%m-%d';
                break;
        }
        return [$startDate, $endDate, $dateFormat];
    }

    private function generatePeriodMap($startDate, $endDate, $period)
    {
        $map = [];
        $start = new \DateTime($startDate);
        $end = new \DateTime($endDate);

        while ($start <= $end) {
            switch ($period) {
                case 'yearly':
                    $map[$start->format('Y')] = 0;
                    $start->modify('+1 year');
                    break;
                case 'monthly':
                    $map[$start->format('Y-m')] = 0;
                    $start->modify('+1 month');
                    break;
                case 'weekly':
                    $year = $start->format('o');
                    $week = $start->format('W');
                    $map["{$year}-{$week}"] = 0;
                    $start->modify('+1 week');
                    break;
                case 'daily':
                default:
                    $map[$start->format('Y-m-d')] = 0;
                    $start->modify('+1 day');
                    break;
            }
        }
        return $map;
    }

    public function getCurrentLoad($departmentId)
    {
        $db = Database::getInstance();
        
        $sql = "SELECT departments.name as dept_name, COUNT(*) as count 
                FROM documents 
                JOIN departments ON documents.current_department_id = departments.id
                WHERE documents.status = 'processing' 
                AND documents.current_department_id IS NOT NULL";
        $params = [];

        if ($departmentId && $departmentId !== 'all') {
            $sql .= " AND documents.current_department_id = :dept_id";
            $params[':dept_id'] = $departmentId;
        }

        $sql .= " GROUP BY dept_name";
        
        $results = $db->query($sql, $params)->fetchAll();
        $departmentLoads = [];
        foreach ($results as $row) {
            $departmentLoads[$row['dept_name']] = (int)$row['count'];
        }

        if (!$departmentId || $departmentId === 'all') {
            $allDepts = $db->query("SELECT name FROM departments")->fetchAll();
            foreach ($allDepts as $dept) {
                if (!isset($departmentLoads[$dept['name']])) {
                    $departmentLoads[$dept['name']] = 0;
                }
            }
        } else {
            $selected = $db->query("SELECT name FROM departments WHERE id = :id", [':id' => $departmentId])->fetch();
            if ($selected) {
                $name = $selected['name'];
                $count = $departmentLoads[$name] ?? 0;
                $departmentLoads = [$name => $count];
            }
        }

        arsort($departmentLoads);

        return [
            'labels' => array_keys($departmentLoads),
            'data' => array_values($departmentLoads),
        ];
    }

    public function getThroughput($period, $departmentId)
    {
        $db = Database::getInstance();
        list($startDate, $endDate, $dateFormat) = $this->getDateParameters($period);
        
        $sql = "SELECT DATE_FORMAT(date, '{$dateFormat}') as period_label, 
                       SUM(total_processing_seconds) as total_seconds,
                       SUM(processed_count + released_count) as total_count 
                FROM daily_department_metrics 
                WHERE date BETWEEN :start AND :end";
        $params = [':start' => $startDate, ':end' => $endDate];

        if ($departmentId && $departmentId !== 'all') {
            $sql .= " AND department_id = :dept_id";
            $params[':dept_id'] = $departmentId;
        }

        $sql .= " GROUP BY period_label ORDER BY period_label";
        $results = $db->query($sql, $params)->fetchAll();
        $periodMap = $this->generatePeriodMap($startDate, $endDate, $period);

        foreach ($results as $row) {
            $label = $row['period_label'];
            if (isset($periodMap[$label])) {
                $totalCount = (int)$row['total_count'];
                $totalSeconds = (int)$row['total_seconds'];
                $avgHours = $totalCount > 0 ? ($totalSeconds / $totalCount) / 3600 : 0;
                $periodMap[$label] = round($avgHours, 2);
            }
        }

        return [
            'labels' => array_keys($periodMap),
            'datasets' => [
                [
                    'label' => 'Average Processing Time (hrs)',
                    'data' => array_values($periodMap),
                    'borderColor' => 'rgba(75, 192, 192, 1)',
                    'backgroundColor' => 'rgba(75, 192, 192, 0.2)',
                    'fill' => true,
                    'tension' => 0.1,
                ]
            ]
        ];
    }

    public function getAvgStepTimeByDepartment($isFull)
    {
        $db = Database::getInstance();
        $sql = "SELECT departments.name, 
                       IFNULL(SUM(total_processing_seconds), 0) as total_seconds,
                       IFNULL(SUM(processed_count + released_count), 0) as total_count 
                FROM departments 
                LEFT JOIN daily_department_metrics ON departments.id = daily_department_metrics.department_id 
                GROUP BY departments.name";

        if ($isFull !== 'full') {
            $sql .= " ORDER BY (IFNULL(SUM(total_processing_seconds) / NULLIF(SUM(processed_count + released_count), 0), 999999)) ASC LIMIT 5";
        }

        $results = $db->query($sql)->fetchAll();
        $processed = [];
        foreach ($results as $row) {
            $count = (int)$row['total_count'];
            $seconds = (int)$row['total_seconds'];
            $avg_hours = $count > 0 ? ($seconds / $count) / 3600 : 0;
            $processed[] = [
                'name' => $row['name'],
                'avg_hours' => $avg_hours,
                'total_count' => $count,
                'total_seconds' => $seconds
            ];
        }

        usort($processed, function($a, $b) {
            if ($a['avg_hours'] == $b['avg_hours']) {
                return strcmp($a['name'], $b['name']);
            }
            return ($a['avg_hours'] < $b['avg_hours']) ? -1 : 1;
        });

        $labels = [];
        $datasetData = [];
        $details = [];
        foreach ($processed as $p) {
            $labels[] = $p['name'];
            $datasetData[] = round($p['avg_hours'], 2);
            $details[] = [
                'name' => $p['name'],
                'avg_hours' => round($p['avg_hours'], 2),
                'total_count' => $p['total_count'],
                'total_seconds' => $p['total_seconds']
            ];
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Average Step Time (hrs)',
                    'data' => $datasetData,
                    'backgroundColor' => 'rgba(14, 165, 233, 0.5)', 
                    'borderColor' => 'rgba(14, 165, 233, 1)',
                    'borderWidth' => 1,
                ],
            ],
            'details' => $details
        ];
    }

    public function getDepartmentalLoadVsTime($period, $departmentId)
    {
        $db = Database::getInstance();
        list($startDate, $endDate, $dateFormat) = $this->getDateParameters($period);

        $sql = "SELECT DATE_FORMAT(date, '{$dateFormat}') as period_label,
                       SUM(received_count) as received_total,
                       SUM(total_processing_seconds) as total_seconds,
                       SUM(processed_count + released_count) as work_total
                FROM daily_department_metrics
                WHERE date BETWEEN :start AND :end";
        $params = [':start' => $startDate, ':end' => $endDate];

        if ($departmentId && $departmentId !== 'all') {
            $sql .= " AND department_id = :dept_id";
            $params[':dept_id'] = $departmentId;
        }

        $sql .= " GROUP BY period_label";
        $results = $db->query($sql, $params)->fetchAll();

        $periodMap = $this->generatePeriodMap($startDate, $endDate, $period);
        $finalData = [];
        foreach (array_keys($periodMap) as $label) {
            $finalData[$label] = ['load' => 0, 'time' => 0];
        }

        foreach ($results as $row) {
            $label = $row['period_label'];
            if (isset($finalData[$label])) {
                $workTotal = (int)$row['work_total'];
                $totalSeconds = (int)$row['total_seconds'];
                $avgHours = $workTotal > 0 ? ($totalSeconds / $workTotal) / 3600 : 0;
                $finalData[$label] = [
                    'load' => (int)$row['received_total'],
                    'time' => round($avgHours, 2)
                ];
            }
        }

        return [
            'labels' => array_keys($finalData),
            'datasets' => [
                [
                    'label' => 'Documents Received',
                    'data' => array_column($finalData, 'load'),
                    'borderColor' => 'rgba(54, 162, 235, 1)',
                    'backgroundColor' => 'rgba(54, 162, 235, 0.2)',
                    'yAxisID' => 'y',
                    'tension' => 0.1,
                ],
                [
                    'label' => 'Avg. Processing Time (hrs)',
                    'data' => array_column($finalData, 'time'),
                    'borderColor' => 'rgba(255, 99, 132, 1)',
                    'backgroundColor' => 'rgba(255, 99, 132, 0.2)',
                    'yAxisID' => 'y1',
                    'tension' => 0.1,
                ]
            ]
        ];
    }

    public function getDeclineTrends($period)
    {
        $db = Database::getInstance();
        list($startDate, $endDate, $dateFormat) = $this->getDateParameters($period);

        $periodMap = $this->generatePeriodMap($startDate, $endDate, $period);
        $finalData = [];
        foreach (array_keys($periodMap) as $label) {
            $finalData[$label] = ['declined' => 0];
        }

        $sqlDeclined = "SELECT DATE_FORMAT(declined_at, '{$dateFormat}') as period_label, COUNT(*) as count 
                        FROM documents 
                        WHERE status = 'declined' AND declined_at BETWEEN :start AND :end 
                        GROUP BY period_label";
        $declinedResults = $db->query($sqlDeclined, [':start' => $startDate, ':end' => $endDate])->fetchAll();

        foreach ($declinedResults as $row) {
            $label = $row['period_label'];
            if (isset($finalData[$label])) {
                $finalData[$label]['declined'] = (int)$row['count'];
            }
        }

        return [
            'labels' => array_keys($finalData),
            'datasets' => [
                [
                    'label' => 'Declined Documents',
                    'data' => array_column($finalData, 'declined'),
                    'borderColor' => '#ef4444', 
                    'backgroundColor' => 'rgba(239, 68, 68, 0.2)',
                    'tension' => 0.1,
                    'fill' => true,
                ],
            ],
        ];
    }

    public function getStatusDistribution()
    {
        $db = Database::getInstance();
        $results = $db->query("SELECT status, COUNT(*) as count FROM documents GROUP BY status")->fetchAll();

        $allStatuses = [
            'pending' => 0,
            'in_transit' => 0,
            'processing' => 0,
            'ready_for_release' => 0,
            'completed' => 0,
            'declined' => 0,
            'frozen' => 0
        ];

        foreach ($results as $row) {
            $status = $row['status'];
            if (isset($allStatuses[$status])) {
                $allStatuses[$status] = (int)$row['count'];
            } else {
                $allStatuses[$status] = (int)$row['count']; // Just in case an unknown status exists
            }
        }

        $colorMap = [
            'pending' => '#f97316', 
            'in_transit' => '#3b82f6', 
            'processing' => '#eab308', 
            'ready_for_release' => '#84cc16', 
            'completed' => '#22c55e', 
            'declined' => '#ef4444', 
            'frozen' => '#64748b', 
        ];

        $labels = [];
        $datasetData = [];
        $backgroundColors = [];

        foreach ($allStatuses as $status => $count) {
            $labels[] = $status;
            $datasetData[] = $count;
            $backgroundColors[] = $colorMap[$status] ?? '#A9A9A9';
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'data' => $datasetData, 
                    'backgroundColor' => $backgroundColors,
                ],
            ],
        ];
    }


    public function getProcessingHotspots()
    {
        $db = Database::getInstance();
        $sql = "SELECT purposes.name as purpose_name, 
                       COUNT(documents.id) as doc_count, 
                       AVG(LEAST(GREATEST(TIMESTAMPDIFF(SECOND, documents.created_at, IFNULL(documents.released_at, NOW())), 0), 604800)) / 3600 as avg_duration_hours 
                FROM documents 
                JOIN purposes ON documents.purpose_id = purposes.id 
                GROUP BY purposes.name 
                ORDER BY doc_count DESC 
                LIMIT 15";
        
        $results = $db->query($sql)->fetchAll();
        
        $colors = [
            'rgba(239, 68, 68, 0.6)', 'rgba(59, 130, 246, 0.6)', 'rgba(16, 185, 129, 0.6)',
            'rgba(245, 158, 11, 0.6)', 'rgba(139, 92, 246, 0.6)', 'rgba(236, 72, 153, 0.6)',
            'rgba(20, 184, 166, 0.6)', 'rgba(249, 115, 22, 0.6)', 'rgba(107, 114, 128, 0.6)',
            'rgba(79, 70, 229, 0.6)', 'rgba(217, 70, 239, 0.6)', 'rgba(101, 163, 13, 0.6)',
            'rgba(2, 132, 199, 0.6)', 'rgba(185, 28, 28, 0.6)', 'rgba(30, 58, 138, 0.6)'
        ];

        $labels = [];
        $docCounts = [];
        $avgHours = [];
        $bgColors = [];
        $borderColors = [];
        
        $count = 0;
        foreach ($results as $row) {
            $labels[] = $row['purpose_name'];
            $docCounts[] = (int)$row['doc_count'];
            $avgHours[] = $row['avg_duration_hours'] !== null ? round($row['avg_duration_hours'], 2) : 'N/A';
            
            $color = $colors[$count % count($colors)];
            $bgColors[] = $color;
            $borderColors[] = str_replace('0.6', '1', $color);
            $count++;
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Document Volume',
                    'data' => $docCounts,
                    'backgroundColor' => $bgColors,
                    'borderColor' => $borderColors,
                    'borderWidth' => 1,
                    'avgHours' => $avgHours,
                ]
            ]
        ];
    }

    public function getSubmissionDistricts()
    {
        $db = Database::getInstance();
        $sql = "SELECT district, COUNT(*) as count 
                FROM documents 
                WHERE district IS NOT NULL 
                GROUP BY district 
                ORDER BY count DESC";
        
        $results = $db->query($sql)->fetchAll();
        $labels = [];
        $dataArr = [];

        foreach ($results as $row) {
            $labels[] = $row['district'];
            $dataArr[] = (int)$row['count'];
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Documents Submitted',
                    'data' => $dataArr,
                    'backgroundColor' => 'rgba(99, 102, 241, 0.5)', 
                    'borderColor' => 'rgba(99, 102, 241, 1)',
                    'borderWidth' => 1,
                ]
            ]
        ];
    }

    public function getPeakIntakeHours(): array
    {
        $db = Database::getInstance();
        
        // Bounded range query using idx_created_at index (last 30 days) to prevent table scan
        $startDate = date('Y-m-d H:i:s', strtotime('-30 days'));
        $sql = "SELECT HOUR(created_at) as hour_num, COUNT(*) as count 
                FROM documents 
                WHERE created_at >= :start 
                GROUP BY hour_num 
                ORDER BY hour_num";
        $results = $db->query($sql, [':start' => $startDate])->fetchAll();

        // Map hours 0-23 to 12-hour labels (e.g. 8 AM, 1 PM)
        $hourMap = [];
        for ($h = 0; $h < 24; $h++) {
            $label = date("g A", strtotime("2026-01-01 {$h}:00:00"));
            $hourMap[$h] = ['label' => $label, 'count' => 0];
        }

        foreach ($results as $row) {
            $h = (int)$row['hour_num'];
            if (isset($hourMap[$h])) {
                $hourMap[$h]['count'] = (int)$row['count'];
            }
        }

        return [
            'labels' => array_column($hourMap, 'label'),
            'datasets' => [
                [
                    'label' => 'Documents Submitted',
                    'data' => array_column($hourMap, 'count'),
                    'backgroundColor' => 'rgba(99, 102, 241, 0.5)',
                    'borderColor' => 'rgba(99, 102, 241, 1)',
                    'borderWidth' => 1,
                ]
            ]
        ];
    }
}
