<?php
define('BASE_PATH', __DIR__);
require 'vendor/autoload.php';
$db = \App\Core\Database::getInstance();
$trackingCode = 'DEPED-044BBD417D';
$stmt = $db->query("SELECT * FROM documents WHERE tracking_code = :tc", [':tc' => $trackingCode]);
$doc = $stmt->fetch();

$logs = $db->query("SELECT id, action, document_state_hash FROM document_logs WHERE document_id = :id ORDER BY id ASC", [':id' => $doc['id']])->fetchAll();
foreach($logs as $l) {
    echo "Log: {$l['action']} - Hash: {$l['document_state_hash']}\n";
}
echo "Document route in DB: " . $doc['finalized_route'] . "\n";
