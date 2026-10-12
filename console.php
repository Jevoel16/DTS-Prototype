<?php

/**
 * Console Worker for processing background jobs.
 * This simulates Laravel's `php artisan queue:work`.
 * Run this script via CLI: `php console.php`
 */

require __DIR__ . '/vendor/autoload.php';

// Match Laravel's default timezone so background hash generation aligns properly
date_default_timezone_set('Asia/Manila');

use App\Core\Database;

define('BASE_PATH', __DIR__);

echo "Starting async queue worker...\n";

$db = Database::getInstance();

$stopWorker = false;

// Memory leak prevention limits
$maxMemory = 8 * 1024 * 1024 * 1024; // 8 GB limit before restarting
$maxJobs = 100; // Restart after 100 jobs to clear any accumulated state
$jobsProcessed = 0;

// Timestamps for container-compatible internal worker task scheduler
$lastSampleTime = time();
$lastBackfillTime = time();
$lastRollupTime = time();
$lastGcTime = time();
$lastStaleCleanupTime = time();

/**
 * Execute periodic system maintenance and telemetry tasks inside the worker loop.
 * Eliminates reliance on host system cron daemons in containerized environments.
 */
function runScheduledTasks(int &$lastSampleTime, int &$lastBackfillTime, int &$lastRollupTime, int &$lastGcTime, int &$lastStaleCleanupTime): void
{
    $now = time();

    // 1. Sample database performance metrics every 5 minutes (300s)
    if ($now - $lastSampleTime >= 300) {
        $lastSampleTime = $now;
        $scriptPath = BASE_PATH . '/scripts/sample-db-metrics.php';
        if (file_exists($scriptPath)) {
            echo "⏰ [Worker Scheduler] Sampling database performance metrics...\n";
            passthru(PHP_BINARY . ' ' . escapeshellarg($scriptPath));
        }
    }

    // 2. Departmental TAT & Volume Metrics Backfill every 30 minutes (1800s)
    if ($now - $lastBackfillTime >= 1800) {
        $lastBackfillTime = $now;
        $scriptPath = BASE_PATH . '/scripts/backfill-metrics.php';
        if (file_exists($scriptPath)) {
            echo "⏰ [Worker Scheduler] Running departmental metrics backfill...\n";
            passthru(PHP_BINARY . ' ' . escapeshellarg($scriptPath));
        }
    }

    // 3. Roll up old database metrics (>24h) into hourly aggregates every 24 hours (86400s)
    if ($now - $lastRollupTime >= 86400) {
        $lastRollupTime = $now;
        $scriptPath = BASE_PATH . '/scripts/rollup-metrics.php';
        if (file_exists($scriptPath)) {
            echo "⏰ [Worker Scheduler] Running historical database metrics rollup...\n";
            passthru(PHP_BINARY . ' ' . escapeshellarg($scriptPath));
        }
    }

    // 4. Response cache GC + log rotation every hour (3600s).
    // Cache serve TTL is 55s, so any file older than 1 hour is guaranteed stale.
    if ($now - $lastGcTime >= 3600) {
        $lastGcTime = $now;

        $cacheDir = BASE_PATH . '/cache/responses';
        $purged = 0;
        if (is_dir($cacheDir)) {
            foreach (glob($cacheDir . '/*.html') as $file) {
                if (time() - filemtime($file) > 3600) {
                    if (@unlink($file)) {
                        $purged++;
                    }
                }
            }
        }
        echo "⏰ [Worker Scheduler] Response cache GC: purged {$purged} stale file(s).\n";

        // Rotate navigation.log when it exceeds 50 MB to prevent unbounded growth
        $navLog = BASE_PATH . '/storage/logs/navigation.log';
        if (file_exists($navLog) && filesize($navLog) > 50 * 1024 * 1024) {
            $archiveName = BASE_PATH . '/storage/logs/navigation-' . date('Y-m-d-His') . '.log';
            rename($navLog, $archiveName);
            echo "⏰ [Worker Scheduler] Rotated navigation.log → " . basename($archiveName) . "\n";
        }
    }

    // 5. Cleanup stale pending documents (>3 days without intake) every 24 hours (86400s)
    if ($now - $lastStaleCleanupTime >= 86400) {
        $lastStaleCleanupTime = $now;
        echo "⏰ [Worker Scheduler] Running stale pending documents cleanup...\n";
        try {
            $job = new \App\Jobs\CleanupStalePendingDocumentsJob();
            $expiredCount = $job->handle(3);
            echo "⏰ [Worker Scheduler] Stale pending cleanup finished. Expired {$expiredCount} document(s).\n";
        } catch (\Throwable $e) {
            echo "⏰ [Worker Scheduler] Stale pending cleanup failed: " . $e->getMessage() . "\n";
        }
    }
}

// Intercepts system termination signals to allow the worker to finish its current job 
// before shutting down gracefully, preventing data corruption mid-task.
if (extension_loaded('pcntl')) {
    pcntl_async_signals(true);
    $signalHandler = function ($signo) use (&$stopWorker) {
        echo "\nReceived shutdown signal ($signo). Finishing current job and stopping...\n";
        $stopWorker = true;
    };
    pcntl_signal(SIGINT, $signalHandler);
    pcntl_signal(SIGTERM, $signalHandler);
} else {
    echo "Warning: PCNTL extension not loaded. Worker cannot shut down gracefully.\n";
}

$maxWorkers = 2; // Can be changed freely
$activeWorkers = [];

// Clean up function to check for dead processes
// Entries are PIDs on Linux, or proc_open handles on Windows (no POSIX extension there)
function cleanupActiveWorkers(array &$activeWorkers) {
    foreach ($activeWorkers as $key => $worker) {
        if (is_resource($worker)) {
            if (!proc_get_status($worker)['running']) {
                proc_close($worker);
                unset($activeWorkers[$key]);
            }
        } elseif (!posix_kill($worker, 0)) {
            // posix_kill($pid, 0) checks if process is alive without sending a signal
            unset($activeWorkers[$key]);
        }
    }
}

// Re-launch this worker in place; without PCNTL (Windows) exit and let the operator restart it
function restartWorker() {
    global $argv;
    if (function_exists('pcntl_exec')) {
        pcntl_exec(PHP_BINARY, $argv);
    }
    echo "PCNTL not available. Worker exiting; please restart it manually.\n";
    exit(0);
}

while (!$stopWorker) {
    // 0. Run scheduled maintenance & telemetry tasks
    runScheduledTasks($lastSampleTime, $lastBackfillTime, $lastRollupTime, $lastGcTime, $lastStaleCleanupTime);

    // 1. Memory Leak Prevention: Check if we exceeded our allowed memory
    if (memory_get_usage(true) > $maxMemory) {
        echo "Memory limit exceeded (" . round(memory_get_usage(true) / 1024 / 1024, 2) . " MB). Restarting worker seamlessly...\n";
        restartWorker();
    }
    
    // 2. Job Count Limit: Check if we processed enough jobs
    if ($jobsProcessed >= $maxJobs) {
        echo "Processed {$maxJobs} jobs. Restarting worker seamlessly...\n";
        restartWorker();
    }

    cleanupActiveWorkers($activeWorkers);

    if (count($activeWorkers) >= $maxWorkers) {
        sleep(1);
        continue;
    }

    // Look for a pending job
    $sql = "SELECT * FROM jobs WHERE reserved_at IS NULL AND available_at <= UNIX_TIMESTAMP() ORDER BY id ASC LIMIT 1";
    $stmt = $db->query($sql);
    $job = $stmt->fetch();

    if ($job) {
        $jobId = $job['id'];
        
        // Claim the job atomically. Using UPDATE ensures that if multiple worker processes 
        // are running concurrently, only one will successfully claim this specific row.
        $db->query("UPDATE jobs SET reserved_at = UNIX_TIMESTAMP(), attempts = attempts + 1 WHERE id = :id AND reserved_at IS NULL", ['id' => $jobId]);
        
        // Ensure we actually reserved it (in case of multiple workers)
        $check = $db->query("SELECT * FROM jobs WHERE id = :id AND reserved_at IS NOT NULL", ['id' => $jobId])->fetch();
        
        if ($check) {
            echo "Dispatching Job ID: {$jobId}\n";
            
            // Dispatch in background
            $pid = 0;
            if (PHP_OS_FAMILY === 'Windows') {
                // cmd.exe has no "&"/"$!" backgrounding, so spawn the runner directly
                $proc = proc_open(
                    [PHP_BINARY, __DIR__ . '/runner.php', (string) (int) $jobId],
                    [0 => ['file', 'NUL', 'r'], 1 => ['file', 'NUL', 'w'], 2 => ['file', 'NUL', 'w']],
                    $pipes
                );
                if (is_resource($proc)) {
                    $pid = proc_get_status($proc)['pid'];
                    $activeWorkers[] = $proc;
                }
            } else {
                $runnerScript = escapeshellarg(__DIR__ . '/runner.php');
                $cmd = PHP_BINARY . " {$runnerScript} " . (int)$jobId . " > /dev/null 2>&1 & echo $!";
                $pid = (int) shell_exec($cmd);
                if ($pid > 0) {
                    $activeWorkers[] = $pid;
                }
            }

            if ($pid > 0) {
                echo "Dispatched to PID: {$pid} (" . count($activeWorkers) . "/{$maxWorkers} active workers)\n";
            } else {
                // Release the reservation so the job isn't stuck forever
                $db->query("UPDATE jobs SET reserved_at = NULL WHERE id = :id", ['id' => $jobId]);
                echo "Failed to dispatch Job ID: {$jobId}. Released back to queue.\n";
            }
            
            $jobsProcessed++;
        }
    } else {
        // Sleep if no jobs
        sleep(2);
    }
}
