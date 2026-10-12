<?php
define('BASE_PATH', dirname(__DIR__));
require __DIR__ . '/../vendor/autoload.php';
use App\Core\Database;

$db = Database::getInstance();
$conn = $db->getConnection();

// Find data older than 24 hours and group by the hour
$stmt = $db->query(
    "SELECT DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00') as hour_group,
            AVG(connections) as avg_conn,
            AVG(avg_query_time_ms) as avg_time,
            SUM(slow_queries) as total_slow
     FROM database_metrics
     WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 DAY)
     GROUP BY hour_group
     HAVING COUNT(*) > 1"
);

$rollups = $stmt->fetchAll();

foreach ($rollups as $row) {
    // Wrap each hour's rollup in a transaction to ensure we don't accidentally 
    // delete raw 5-minute data if the aggregate insert fails.
    $conn->beginTransaction();
    try {
        // 1. Delete the raw 5-minute data for this specific hour
        $db->query(
            "DELETE FROM database_metrics
             WHERE DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00') = :hour",
            ['hour' => $row['hour_group']]
        );

        // 2. Insert the single aggregated hourly row
        $db->query(
            "INSERT INTO database_metrics (connections, avg_query_time_ms, slow_queries, created_at)
             VALUES (:conn, :time, :slow, :created_at)",
            [
                'conn' => $row['avg_conn'],
                'time' => $row['avg_time'],
                'slow' => $row['total_slow'],
                'created_at' => $row['hour_group']
            ]
        );
        $conn->commit();
    } catch (\Exception $e) {
        $conn->rollBack();
    }
}

echo "Metric rollup complete.\n";
