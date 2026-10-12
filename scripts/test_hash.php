<?php
require 'vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$db = new PDO("mysql:host={$_ENV['DB_HOST']};port={$_ENV['DB_PORT']};dbname={$_ENV['DB_DATABASE']}", $_ENV['DB_USERNAME'], $_ENV['DB_PASSWORD']);

$log = $db->query("SELECT * FROM document_logs ORDER BY id ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);

$expectedPreviousHash = 'genesis_hash';
$timestampForHashing = date('c', strtotime($log['created_at']));

$dataToHash = [
    (int) $log['document_id'],
    $log['user_id'] ? (int) $log['user_id'] : '',
    $log['action'],
    $timestampForHashing,
    $expectedPreviousHash,
    $log['document_state_hash'],
    $log['signature']
];

$recalculatedHash = hash('sha256', json_encode($dataToHash));

echo "DB ID: " . $log['id'] . "\n";
echo "DB Hash: " . $log['hash'] . "\n";
echo "Recalculated Hash: " . $recalculatedHash . "\n";
echo "JSON Encoded (PHP): " . json_encode($dataToHash) . "\n";
