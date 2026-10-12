<?php
define('BASE_PATH', __DIR__);
require 'vendor/autoload.php';
$db = \App\Core\Database::getInstance();
$stmt = $db->query("SELECT * FROM documents WHERE status = 'completed' LIMIT 1");
$doc = $stmt->fetch();
if (!$doc) { die("No completed doc found\n"); }

$logs = $db->query("SELECT id, action, document_state_hash FROM document_logs WHERE document_id = :id ORDER BY id ASC", [':id' => $doc['id']])->fetchAll();
foreach($logs as $l) {
    echo "Log: {$l['action']} - Hash: {$l['document_state_hash']}\n";
}

$currentStateHash = \App\Core\IntegrityManager::calculateStateHash($doc);
echo "Current Hash (Raw DB string): $currentStateHash\n";

// Now test with forced array decoding
$docArray = $doc;
$docArray['finalized_route'] = json_decode($doc['finalized_route'], true);
$forcedArrayHash = \App\Core\IntegrityManager::calculateStateHash($docArray);
echo "Current Hash (Forced Array->json_encode): $forcedArrayHash\n";

