<?php

/**
 * Job Runner Script
 * Executed as a separate process by console.php for true concurrency.
 */

require __DIR__ . '/vendor/autoload.php';
date_default_timezone_set('Asia/Manila');

use App\Core\Database;

define('BASE_PATH', __DIR__);

if (!isset($argv[1])) {
    echo "Usage: php runner.php <job_id>\n";
    exit(1);
}

$jobId = (int) $argv[1];
$db = Database::getInstance();

// Double check the job exists and is reserved
$job = $db->query("SELECT * FROM jobs WHERE id = :id AND reserved_at IS NOT NULL", ['id' => $jobId])->fetch();

if (!$job) {
    echo "Job ID: {$jobId} not found or not reserved.\n";
    exit(0);
}

try {
    $payload = json_decode($job['payload'], true);
    
    $jobClass = $payload['class'];
    $jobData = $payload['data'] ?? [];

    if (class_exists($jobClass)) {
        $instance = new $jobClass(...array_values($jobData));
        if (method_exists($instance, 'handle')) {
            $instance->handle();
        }
    } else {
        throw new Exception("Job class {$jobClass} not found.");
    }

    // Success: Delete job
    $db->query("DELETE FROM jobs WHERE id = :id", ['id' => $jobId]);
    echo "Job ID: {$jobId} processed successfully.\n";

} catch (\Throwable $e) {
    echo "Job ID: {$jobId} failed: " . $e->getMessage() . "\n";
    
    // Log failure
    $db->query(
        "INSERT INTO failed_jobs (uuid, connection, queue, payload, exception, failed_at) VALUES (:uuid, :connection, :queue, :payload, :exception, NOW())",
        [
            'uuid' => uniqid(),
            'connection' => 'database',
            'queue' => $job['queue'],
            'payload' => $job['payload'],
            'exception' => (string) $e
        ]
    );
    
    // Delete from queue
    $db->query("DELETE FROM jobs WHERE id = :id", ['id' => $jobId]);
    exit(1);
}

exit(0);
