<?php

/**
 * Concurrency Test Script
 * Simulates several users completing the same task at the same moment, to check that the
 * optimistic lock (UPDATE ... WHERE version = :version) lets exactly one of them succeed.
 *
 *   php scripts/concurrency_test.php <document_id> <signing PIN> [workers=5]
 *
 * The document must be in `processing`; the workers act as a user of the department that
 * holds the current step, so the PIN must be that user's signing PIN. Workers are separate
 * PHP processes (proc_open), so this runs the same on Windows and Linux; they wait for a
 * shared start time before acting, so their requests really overlap.
 */

require __DIR__ . '/../vendor/autoload.php';

define('BASE_PATH', dirname(__DIR__));
refuse_in_production();
date_default_timezone_set('Asia/Manila');

use App\Core\Database;

// --- Worker mode: one simulated user (started by the parent below) ---------------------------
if (($argv[1] ?? '') === '--worker') {
    [, , $documentId, $userId, $startAt, $workerNo] = $argv;
    $pin = getenv('DTS_TEST_PIN') ?: '';
    try {
        // Everything slow (DB connection, user lookup) happens before the shared start time
        $workflow = new \App\Services\DocumentWorkflowService();
        $user = \App\Models\User::findById((int) $userId);
        while (microtime(true) < (float) $startAt) {
            usleep(500);
        }
        $workflow->completeTask((int) $documentId, "Worker $workerNo concurrent processing test", $user, $pin);
        echo "✅ [Worker $workerNo] SUCCESS: Task completed.\n";
    } catch (\Throwable $e) {
        echo "❌ [Worker $workerNo] REJECTED: " . $e->getMessage() . "\n";
    }
    exit(0);
}

// --- Parent mode ---------------------------------------------------------------------------
if ($argc < 3) {
    echo "Usage: php scripts/concurrency_test.php <document_id> <signing PIN> [workers=5]\n";
    exit(1);
}

$documentId = (int) $argv[1];
$pin = $argv[2];
$numWorkers = max(2, (int) ($argv[3] ?? 5));

echo "🚀 Starting Concurrency Test on Document ID: $documentId\n";

$db = Database::getInstance();
$doc = $db->query("SELECT * FROM documents WHERE id = :id", [':id' => $documentId])->fetch();

if (!$doc) {
    echo "❌ Document not found.\n";
    exit(1);
}

echo "Current Status: {$doc['status']}\n";
echo "Current Step: {$doc['current_step']}\n";

if ($doc['status'] !== 'processing') {
    echo "❌ The document must be in 'processing' (received by a department) for this test.\n";
    exit(1);
}

// A user of the department holding the current step (completeTask only accepts that department)
$user = $db->query(
    "SELECT id, email FROM users WHERE department_id = :dept_id AND deactivated_at IS NULL AND security_key_set_at IS NOT NULL ORDER BY id LIMIT 1",
    [':dept_id' => $doc['current_department_id']]
)->fetch();
if (!$user) {
    echo "❌ No active user with a signing key in the department holding this step.\n";
    exit(1);
}
echo "Acting as: {$user['email']}\n";

// Start all workers, then release them together 2 seconds from now
$startAt = microtime(true) + 2.0;
$env = getenv();
$env['DTS_TEST_PIN'] = $pin; // passed via the environment, never on the command line
$processes = [];
$pipes = [];

echo "\nStarting $numWorkers concurrent workers...\n";
for ($i = 0; $i < $numWorkers; $i++) {
    $processes[$i] = proc_open(
        [PHP_BINARY, __FILE__, '--worker', (string) $documentId, (string) $user['id'], sprintf('%.6F', $startAt), (string) $i],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes[$i],
        null,
        $env
    );
    if (!is_resource($processes[$i])) {
        die("Could not start worker $i\n");
    }
    fclose($pipes[$i][0]);
}

// Collect each worker's output and wait for it to exit
foreach ($processes as $i => $process) {
    echo stream_get_contents($pipes[$i][1]);
    $stderr = trim(stream_get_contents($pipes[$i][2]));
    if ($stderr !== '') {
        echo "   [Worker $i stderr] $stderr\n";
    }
    fclose($pipes[$i][1]);
    fclose($pipes[$i][2]);
    proc_close($process);
}

echo "\n🏁 Concurrency test completed.\n";
echo "Checking that the document advanced exactly ONE step despite $numWorkers concurrent requests...\n";

$docAfter = $db->query("SELECT * FROM documents WHERE id = :id", [':id' => $documentId])->fetch();
$newLogs = (int) $db->query(
    "SELECT COUNT(*) AS c FROM document_logs WHERE document_id = :id AND action = 'Processing Complete' AND created_at >= FROM_UNIXTIME(:t)",
    [':id' => $documentId, ':t' => (int) floor($startAt) - 1]
)->fetch()['c'];

echo "New Status: {$docAfter['status']}\n";
echo "New Step: {$docAfter['current_step']}\n";
echo "New 'Processing Complete' log entries: $newLogs\n";

$expectedStep = $doc['current_step'] + 1;
if ((int) $docAfter['current_step'] === $expectedStep && $newLogs === 1) {
    echo "\n🎉 TEST PASSED! Exactly one request won; the optimistic lock rejected the others.\n";
    exit(0);
}
if ((int) $docAfter['current_step'] === (int) $doc['current_step']) {
    echo "\n⚠️ TEST INCONCLUSIVE: no request succeeded. Check the PIN and the messages above.\n";
    exit(1);
}
echo "\n⚠️ TEST FAILED! The document advanced more than one step or logged more than one completion.\n";
exit(1);
