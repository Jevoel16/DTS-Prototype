<?php
/**
 * Seals unsealed document_logs rows with APP_INTEGRITY_KEY (HMAC over each block hash).
 * Only documents whose hash chain still verifies are sealed; broken chains are listed instead,
 * so tampered history is never sealed.
 *
 * Run once after setting APP_INTEGRITY_KEY, and after seeding (the seeders call it):
 *   composer run seal
 */
define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/vendor/autoload.php';

// Must match public/index.php: hashes include timestamps
date_default_timezone_set('Asia/Manila');

use App\Core\IntegrityManager;

if (IntegrityManager::integrityKey() === '') {
    echo "⚠️  APP_INTEGRITY_KEY is not set in .env; skipping log sealing.\n";
    echo "   Generate one with: php -r \"echo bin2hex(random_bytes(32)), PHP_EOL;\"\n";
    exit(0);
}

echo "🔏 Sealing document logs...\n";
$result = IntegrityManager::sealPendingLogs();
echo "   Sealed " . number_format($result['sealed']) . " log(s).\n";

if (!empty($result['skipped_documents'])) {
    $count = count($result['skipped_documents']);
    echo "   ⚠️  {$count} document(s) have a broken hash chain and were NOT sealed. Investigate them first:\n";
    echo "   document ids: " . implode(', ', array_slice($result['skipped_documents'], 0, 50)) . ($count > 50 ? ', ...' : '') . "\n";
    exit(1);
}
