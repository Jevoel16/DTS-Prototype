<?php
// Define BASE_PATH so the application config works correctly
define('BASE_PATH', dirname(__DIR__));

// Require Composer autoloader if it exists (for standard setup)
if (file_exists(BASE_PATH . '/vendor/autoload.php')) {
    require BASE_PATH . '/vendor/autoload.php';
}

require BASE_PATH . '/src/Jobs/CreateBackupJob.php';

echo "Starting daily automated backup...\n";
try {
    $job = new \App\Jobs\CreateBackupJob();
    $job->handle();
    echo "Backup completed successfully and stored in storage/app/backups/.\n";
} catch (\Exception $e) {
    echo "Backup failed: " . $e->getMessage() . "\n";
    exit(1);
}
