<?php
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config/config.php';
require __DIR__ . '/../src/Core/Database.php';

use App\Core\Database;
use App\Models\User;
use App\Services\DocumentWorkflowService;

$db = Database::getInstance();

// 1. Create a dummy user
$db->query("INSERT INTO users (name, email, role, department_id, password_hash) VALUES ('Test User', 'test@example.com', 'admin', 1, 'hash')");
$userId = $db->getConnection()->lastInsertId();
$user = User::findById($userId);

// 2. Create a dummy purpose
$db->query("INSERT INTO purposes (name, is_official) VALUES ('Test Purpose', 0)");
$purposeId = $db->getConnection()->lastInsertId();

// 3. Create a dummy document
$trackingCode = 'DEPED-TEST-' . time();
$db->query("INSERT INTO documents (tracking_code, title, status, version, purpose_id) VALUES (:tc, 'Test Doc', 'pending', 1, :pid)", [
    'tc' => $trackingCode,
    'pid' => $purposeId
]);
$docId = $db->getConnection()->lastInsertId();

echo "Document created. ID: $docId, Version: 1\n";

// 4. Test workflow (Receive)
$workflow = new DocumentWorkflowService();
try {
    $workflow->scanDocument($trackingCode, $user);
    echo "Scan successful.\n";
    
    $doc = $db->query("SELECT version, status FROM documents WHERE id = $docId")->fetch();
    echo "New Version: " . $doc['version'] . ", Status: " . $doc['status'] . "\n";
    
    // Test optimistic locking exception by manually passing an outdated document or simulating a concurrent update
    $db->query("UPDATE documents SET version = 999 WHERE id = $docId");
    
    echo "Simulating concurrent update (version changed to 999 behind the scenes).\n";
    
    // Now try to process it
    $workflow->completeTask($docId, '', $user, 'password'); // 'password' is not verified in this mocked state since we skip pin validation in test if we don't care, but wait, completeTask does require valid pin? No, it's passed to workflow.
    
    echo "FAIL: Optimistic lock did not trigger.\n";
} catch (Exception $e) {
    if (str_contains($e->getMessage(), 'modified by another user')) {
        echo "SUCCESS: Optimistic lock triggered correctly! Message: " . $e->getMessage() . "\n";
    } else {
        echo "Failed with different exception: " . $e->getMessage() . "\n";
    }
}

// Cleanup
$db->query("DELETE FROM document_logs WHERE document_id = $docId");
$db->query("DELETE FROM documents WHERE id = $docId");
$db->query("DELETE FROM users WHERE id = $userId");
$db->query("DELETE FROM purposes WHERE id = $purposeId");
?>
