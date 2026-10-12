<?php

namespace App\Services;

use App\Core\Database;

class DatabasePerformanceService
{
    /**
     * Get database performance metrics.
     *
     * @param  string  $period
     * @return array
     */
    public function getPerformanceMetrics($period = 'now')
    {
        return [
            'connections' => $this->getConnections(),
            'avg_query_time' => $this->getAverageQueryTime($period),
            'slow_queries' => $this->getSlowQueries($period),
        ];
    }

    /**
     * Get the current number of database connections.
     *
     * @return int
     */
    protected function getConnections()
    {
        try {
            $db = Database::getInstance();
            $stmt = $db->query("SHOW STATUS WHERE `variable_name` = 'Threads_connected'");
            $result = $stmt->fetch();
            return isset($result['Value']) ? (int) $result['Value'] : 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get the average query time. (Placeholder)
     *
     * @param  string  $period
     * @return float
     */
    protected function getAverageQueryTime($period)
    {
        return rand(10, 50) / 1000; // Random value in ms
    }

    /**
     * Get the number of slow queries. (Placeholder)
     *
     * @param  string  $period
     * @return int
     */
    protected function getSlowQueries($period)
    {
        return rand(0, 5);
    }

    /**
     * Get historical data for the charts.
     *
     * @param string $period
     * @return array
     */
    public function getChartData($period = 'daily')
    {
        $db = Database::getInstance();
        $now = time();

        $buckets = [];
        $labelToSqlMatch = [];

        if ($period === 'hourly') {
            // Last 24 hours: 24 discrete 1-hour buckets
            for ($i = 23; $i >= 0; $i--) {
                $ts = strtotime("-{$i} hours", $now);
                $label = date('H:00', $ts);
                $dateKey = date('Y-m-d H', $ts);
                $buckets[$label] = ['connections' => 0, 'avg_query_time' => 0.0, 'slow_queries' => 0];
                $labelToSqlMatch[$dateKey] = $label;
            }

            $sql = "SELECT 
                        DATE_FORMAT(created_at, '%Y-%m-%d %H') as match_key,
                        AVG(connections) as avg_connections,
                        AVG(avg_query_time_ms) as avg_query_time,
                        SUM(slow_queries) as total_slow_queries
                    FROM database_metrics
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                    GROUP BY match_key";
        } elseif ($period === 'weekly') {
            // Last 12 weeks: 12 weekly buckets
            for ($i = 11; $i >= 0; $i--) {
                $ts = strtotime("-{$i} weeks", $now);
                $label = date('M d', $ts) . ' (W' . date('W', $ts) . ')';
                $weekKey = date('o-W', $ts); // ISO-8601 year and week number
                $buckets[$label] = ['connections' => 0, 'avg_query_time' => 0.0, 'slow_queries' => 0];
                $labelToSqlMatch[$weekKey] = $label;
            }

            $sql = "SELECT 
                        DATE_FORMAT(created_at, '%x-%v') as match_key,
                        AVG(connections) as avg_connections,
                        AVG(avg_query_time_ms) as avg_query_time,
                        SUM(slow_queries) as total_slow_queries
                    FROM database_metrics
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 WEEK)
                    GROUP BY match_key";
        } elseif ($period === 'monthly') {
            // Last 12 months: 12 monthly buckets
            for ($i = 11; $i >= 0; $i--) {
                $ts = strtotime("-{$i} months", $now);
                $label = date('M Y', $ts);
                $monthKey = date('Y-m', $ts);
                $buckets[$label] = ['connections' => 0, 'avg_query_time' => 0.0, 'slow_queries' => 0];
                $labelToSqlMatch[$monthKey] = $label;
            }

            $sql = "SELECT 
                        DATE_FORMAT(created_at, '%Y-%m') as match_key,
                        AVG(connections) as avg_connections,
                        AVG(avg_query_time_ms) as avg_query_time,
                        SUM(slow_queries) as total_slow_queries
                    FROM database_metrics
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                    GROUP BY match_key";
        } else {
            // Default (daily): Last 30 days: 30 daily buckets
            for ($i = 29; $i >= 0; $i--) {
                $ts = strtotime("-{$i} days", $now);
                $label = date('M d', $ts);
                $dayKey = date('Y-m-d', $ts);
                $buckets[$label] = ['connections' => 0, 'avg_query_time' => 0.0, 'slow_queries' => 0];
                $labelToSqlMatch[$dayKey] = $label;
            }

            $sql = "SELECT 
                        DATE_FORMAT(created_at, '%Y-%m-%d') as match_key,
                        AVG(connections) as avg_connections,
                        AVG(avg_query_time_ms) as avg_query_time,
                        SUM(slow_queries) as total_slow_queries
                    FROM database_metrics
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    GROUP BY match_key";
        }

        $stmt = $db->query($sql);
        $results = $stmt->fetchAll();

        foreach ($results as $row) {
            $key = $row['match_key'] ?? '';
            if (isset($labelToSqlMatch[$key])) {
                $lbl = $labelToSqlMatch[$key];
                $buckets[$lbl]['connections'] = round((float)$row['avg_connections'], 2);
                $buckets[$lbl]['avg_query_time'] = round((float)$row['avg_query_time'], 4);
                $buckets[$lbl]['slow_queries'] = (int)$row['total_slow_queries'];
            }
        }

        $labels = [];
        $connectionsData = [];
        $avgQueryTimeData = [];
        $slowQueriesData = [];

        foreach ($buckets as $lbl => $data) {
            $labels[] = $lbl;
            $connectionsData[] = $data['connections'];
            $avgQueryTimeData[] = $data['avg_query_time'];
            $slowQueriesData[] = $data['slow_queries'];
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Connections',
                    'data' => $connectionsData,
                    'borderColor' => 'rgba(75, 192, 192, 1)',
                    'backgroundColor' => 'rgba(75, 192, 192, 0.2)',
                    'yAxisID' => 'y',
                    'tension' => 0.1
                ],
                [
                    'label' => 'Avg Query Time (ms)',
                    'data' => $avgQueryTimeData,
                    'borderColor' => 'rgba(255, 159, 64, 1)',
                    'backgroundColor' => 'rgba(255, 159, 64, 0.2)',
                    'yAxisID' => 'y1',
                    'tension' => 0.1
                ],
                [
                    'label' => 'Slow Queries',
                    'data' => $slowQueriesData,
                    'borderColor' => 'rgba(255, 99, 132, 1)',
                    'backgroundColor' => 'rgba(255, 99, 132, 0.2)',
                    'yAxisID' => 'y2',
                    'type' => 'bar',
                ]
            ]
        ];
    }
}
