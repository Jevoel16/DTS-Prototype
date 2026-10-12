<?php

namespace App\Services;

use App\Core\Database;

class DepartmentAnalyticsService
{
    public function getAverageProcessingTime($departmentId, $period)
    {
        $db = Database::getInstance();
        list($startDate, $endDate, $dateFormat) = $this->getDateParameters($period);

        $sql = "SELECT 
                    DATE_FORMAT(date, '{$dateFormat}') as period_label,
                    SUM(total_processing_seconds) as total_seconds,
                    SUM(processed_count + released_count) as total_count
                FROM daily_department_metrics
                WHERE department_id = :dept_id AND date BETWEEN :start_date AND :end_date
                GROUP BY period_label";

        $stmt = $db->query($sql, [
            ':dept_id' => $departmentId,
            ':start_date' => $startDate,
            ':end_date' => $endDate
        ]);

        $results = $stmt->fetchAll();
        $periodMap = $this->generatePeriodMap($startDate, $endDate, $period);

        foreach ($results as $row) {
            if (isset($periodMap[$row['period_label']])) {
                $totalCount = (int)$row['total_count'];
                $totalSeconds = (int)$row['total_seconds'];
                // We use 3600 to convert seconds to hours for display
                $avgHours = $totalCount > 0 ? ($totalSeconds / $totalCount) / 3600 : 0;
                $periodMap[$row['period_label']] = round($avgHours, 2);
            }
        }

        return [
            'labels' => array_keys($periodMap),
            'data' => array_values($periodMap),
        ];
    }

    public function getMetricTimeSeries($departmentId, $period, $sumColumn)
    {
        $db = Database::getInstance();
        list($startDate, $endDate, $dateFormat) = $this->getDateParameters($period);

        $sql = "SELECT 
                    DATE_FORMAT(date, '{$dateFormat}') as period_label,
                    SUM({$sumColumn}) as metric_count
                FROM daily_department_metrics
                WHERE department_id = :dept_id AND date BETWEEN :start_date AND :end_date
                GROUP BY period_label";

        $stmt = $db->query($sql, [
            ':dept_id' => $departmentId,
            ':start_date' => $startDate,
            ':end_date' => $endDate
        ]);

        $results = $stmt->fetchAll();
        $periodMap = $this->generatePeriodMap($startDate, $endDate, $period);

        foreach ($results as $row) {
            if (isset($periodMap[$row['period_label']])) {
                $periodMap[$row['period_label']] = (int) $row['metric_count'];
            }
        }

        return [
            'labels' => array_keys($periodMap),
            'data' => array_values($periodMap),
        ];
    }

    private function getDateParameters($period)
    {
        $endDate = new \DateTime();
        $startDate = clone $endDate;

        switch ($period) {
            case 'weekly':
                $startDate->modify('-4 weeks');
                $dateFormat = '%Y-%v';
                break;
            case 'monthly':
                $startDate->modify('-12 months');
                $dateFormat = '%Y-%m';
                break;
            case 'yearly':
                $startDate->modify('-5 years');
                $dateFormat = '%Y';
                break;
            default: // daily
                $startDate->modify('-30 days');
                $dateFormat = '%Y-%m-%d';
                break;
        }

        return [$startDate->format('Y-m-d'), $endDate->format('Y-m-d'), $dateFormat];
    }

    private function getPhpDateFormat($period)
    {
        switch ($period) {
            case 'weekly': return 'Y-W';
            case 'monthly': return 'Y-m';
            case 'yearly': return 'Y';
            default: return 'Y-m-d';
        }
    }

    private function generatePeriodMap($startDateStr, $endDateStr, $period)
    {
        $periodMap = [];
        $current = new \DateTime($startDateStr);
        $endDate = new \DateTime($endDateStr);
        $format = $this->getPhpDateFormat($period);

        while ($current <= $endDate) {
            $periodMap[$current->format($format)] = 0;
            switch ($period) {
                case 'weekly': $current->modify('+1 week'); break;
                case 'monthly': $current->modify('+1 month'); break;
                case 'yearly': $current->modify('+1 year'); break;
                default: $current->modify('+1 day'); break;
            }
        }
        return $periodMap;
    }
}
