<?php

// Front Controller environment setup so classes load correctly
define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/vendor/autoload.php';

use App\Core\Database;

if ($argc < 2) {
    echo "Usage: php scripts/corrupt.php <tracking_code>\n";
    echo "Or: composer run corrupt -- <tracking_code>\n";
    exit(1);
}

$trackingCode = $argv[1];
$db = Database::getInstance();

$documentData = $db->query("SELECT * FROM documents WHERE tracking_code = :tc", [':tc' => $trackingCode])->fetch();

if (!$documentData) {
    echo "Document $trackingCode not found.\n";
    exit(1);
}

// Tamper with the title and guest_info metadata
$newTitle = "[TAMPERED] " . $documentData['title'];

$guestInfo = json_decode($documentData['guest_info'], true) ?? [];
$guestInfo['name'] = "[TAMPERED] " . ($guestInfo['name'] ?? 'Unknown');
$emailParts = explode('@', $guestInfo['email'] ?? 'test@example.com');
$guestInfo['email'] = "tampered@" . ($emailParts[1] ?? 'example.com');
$newGuestInfo = json_encode($guestInfo);

$db->query("UPDATE documents SET title = :t, guest_info = :g WHERE tracking_code = :tc", [
    ':t' => $newTitle,
    ':g' => $newGuestInfo,
    ':tc' => $trackingCode
]);

echo "\n=============================================\n";
echo "❌ SUCCESS: DATABASE TAMPERING INJECTED\n";
echo "=============================================\n";
echo "Document: $trackingCode\n";
echo "Old Title: {$documentData['title']}\n";
echo "New Title: $newTitle\n";
echo "\nThe live state of the document has been secretly altered.\n";
echo "The cryptographic log chain does NOT know about this change.\n";
echo "\nNext Steps to Test Active Guard / Auto-Freeze:\n";
echo "1. Go to the web application as a staff/officer.\n";
echo "2. Attempt to 'Scan' or 'Process' or 'Manage' document $trackingCode.\n";
echo "3. The Active Guard should intercept the action, deny it, output an error to the console, and Auto-Freeze the document.\n";
echo "=============================================\n";
