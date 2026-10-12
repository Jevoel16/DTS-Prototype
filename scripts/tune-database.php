<?php

require __DIR__ . '/../vendor/autoload.php';

// Load environment variables if needed
if (file_exists(__DIR__ . '/../.env')) {
    $lines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            putenv(trim($name) . '=' . trim($value, " \t\n\r\0\x0B\"'"));
        }
    }
}

// Connect to Database
$host = getenv('DB_HOST');
$user = getenv('DB_USERNAME');
$pass = getenv('DB_PASSWORD');

echo "Tuning database for 1,000,000 document load...\n";

try {
    $pdo = new PDO("mysql:host=$host", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Set Buffer Pool to 4GB
    $pdo->exec("SET GLOBAL innodb_buffer_pool_size = 4294967296;");
    echo "✔ InnoDB Buffer Pool set to 4GB (RAM)\n";

    // Try setting log file size
    try {
        $pdo->exec("SET GLOBAL innodb_log_file_size = 1073741824;");
        echo "✔ InnoDB Log File Size set to 1GB (Performance)\n";
    } catch (PDOException $e) {
        echo "⚠ Note: Log file size tuning skipped (requires restart on this MySQL version or different privilege).\n";
    }

    echo "Database tuning successful!\n";

} catch (PDOException $e) {
    echo "Failed to tune database: " . $e->getMessage() . "\n";
    echo "Suggestion: Run 'sudo service mysql restart' after editing my.cnf manually.\n";
}
