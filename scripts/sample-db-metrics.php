<?php
/**
 * Production Telemetry Sampler for Database Performance Metrics.
 * 
 * Periodically records live MySQL performance metrics (active connection threads,
 * sample query latency, and slow query counters) into the database_metrics table.
 * 
 * Recommended Production Cron (Every 5 minutes):
 * * /5 * * * * php /path/to/project/scripts/sample-db-metrics.php >> /var/log/dts-telemetry.log 2>&1
 */

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/vendor/autoload.php';

use App\Core\Database;

try {
    $db = Database::getInstance();
    
    // 1. Fetch current active connection thread count
    $stmt = $db->query("SHOW STATUS WHERE Variable_name = 'Threads_connected'");
    $res = $stmt->fetch();
    $connections = isset($res['Value']) ? (int)$res['Value'] : 1;

    // 2. Fetch global slow query counter
    $stmtSlow = $db->query("SHOW GLOBAL STATUS WHERE Variable_name = 'Slow_queries'");
    $resSlow = $stmtSlow->fetch();
    $slowQueries = isset($resSlow['Value']) ? (int)$resSlow['Value'] : 0;

    // 3. Measure database ping latency (query execution time in milliseconds)
    $startTime = microtime(true);
    $db->query("SELECT 1");
    $queryTimeMs = round((microtime(true) - $startTime) * 1000, 4);

    // 4. Record sample snapshot into database_metrics table
    $db->query(
        "INSERT INTO database_metrics (connections, avg_query_time_ms, slow_queries, created_at) VALUES (:conn, :time, :slow, NOW())",
        [
            'conn' => $connections,
            'time' => $queryTimeMs,
            'slow' => $slowQueries
        ]
    );

    echo "Database metrics sampled successfully: {$connections} active connections, {$queryTimeMs}ms latency.\n";
} catch (\Throwable $e) {
    error_log("Failed to sample database metrics: " . $e->getMessage());
    echo "Error sampling database metrics: " . $e->getMessage() . "\n";
}
