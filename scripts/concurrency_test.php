<?php

/**
 * Concurrency Test Script
 * Simulates multiple users attempting to process the same document simultaneously
 * to ensure that FOR UPDATE row-level locking handles the race condition perfectly.
 */

require __DIR__ . '/../vendor/autoload.php';

define('BASE_PATH', dirname(__DIR__));
date_default_timezone_set('Asia/Manila');

use App\Core\Database;

if ($argc < 2) {
    echo "Usage: php scripts/concurrency_test.php <document_id>\n";
    exit(1);
}

$documentId = (int)$argv[1];

echo "🚀 Starting Concurrency Test on Document ID: $documentId\n";

$db = Database::getInstance();
$doc = $db->query("SELECT * FROM documents WHERE id = :id", [':id' => $documentId])->fetch();

if (!$doc) {
    echo "❌ Document not found.\n";
    exit(1);
}

echo "Current Status: {$doc['status']}\n";
echo "Current Step: {$doc['current_step']}\n";

// We will fork 5 processes to simulate 5 users clicking "Complete Task" at the exact same millisecond.
$numWorkers = 5;
$pids = [];

echo "\nForking $numWorkers concurrent requests...\n";

for ($i = 0; $i < $numWorkers; $i++) {
    $pid = pcntl_fork();
    if ($pid == -1) {
        die('Could not fork');
    } else if ($pid) {
        // Parent
        $pids[] = $pid;
    } else {
        // Child process
        try {
            // Force a new database connection for each child to simulate separate HTTP requests
            // By resetting the Singleton instance using Reflection
            $reflection = new \ReflectionClass(\App\Core\Database::class);
            $instanceProp = $reflection->getProperty('instance');
            $instanceProp->setAccessible(true);
            $instanceProp->setValue(null);
            
            $db = Database::getInstance();
            $workflow = new \App\Services\DocumentWorkflowService();
            
            // We use a mock user (Officer/Staff) who is assigned to this department
            // In a real test, we might fetch a real user assigned to this dept.
            $stmt = $db->query("SELECT * FROM users WHERE department_id = :dept_id LIMIT 1", [':dept_id' => $doc['current_department_id']]);
            $mockUserArray = $stmt->fetch();
            if (!$mockUserArray) {
                // Fallback to user ID 1
                $mockUserArray = $db->query("SELECT * FROM users WHERE id = 1")->fetch();
            }
            
            $mockUser = \App\Models\User::findById($mockUserArray['id']);
            
            echo "[Worker $i] Attempting to complete task...\n";
            $workflow->completeTask($documentId, "Worker $i concurrent processing test", $mockUser, '123456');
            
            echo "✅ [Worker $i] SUCCESS: Task completed successfully.\n";
        } catch (\Exception $e) {
            echo "❌ [Worker $i] FAILED: " . $e->getMessage() . "\n";
        }
        exit(0);
    }
}

// Wait for all children to finish
foreach ($pids as $pid) {
    pcntl_waitpid($pid, $status);
}

echo "\n🏁 Concurrency test completed.\n";
echo "Checking Document state to ensure it only advanced ONE step, despite 5 concurrent requests...\n";

// Reconnect in the parent
$reflection = new \ReflectionClass(\App\Core\Database::class);
$instanceProp = $reflection->getProperty('instance');
$instanceProp->setAccessible(true);
$instanceProp->setValue(null);
$db = Database::getInstance();

$docAfter = $db->query("SELECT * FROM documents WHERE id = :id", [':id' => $documentId])->fetch();
echo "New Status: {$docAfter['status']}\n";
echo "New Step: {$docAfter['current_step']}\n";

$expectedStep = $doc['current_step'] + 1;
if ($docAfter['current_step'] == $expectedStep) {
    echo "\n🎉 TEST PASSED! The FOR UPDATE lock perfectly prevented race conditions.\n";
} else {
    echo "\n⚠️ TEST FAILED! The document advanced multiple steps or state is corrupted.\n";
}
