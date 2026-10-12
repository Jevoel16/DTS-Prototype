<?php

namespace App\Jobs;

class CreateBackupJob
{
    public function handle(): void
    {
        ini_set('memory_limit', '2G');
        set_time_limit(1200);

        // Simple mysqldump wrapper
        $config = require BASE_PATH . '/src/Config/config.php';
        $dbConfig = $config['database'];
        $host = escapeshellarg($dbConfig['host']);
        $user = escapeshellarg($dbConfig['user']);
        $pass = escapeshellarg($dbConfig['password']);
        $dbname = escapeshellarg($dbConfig['dbname']);

        $backupDir = BASE_PATH . '/storage/app/backups';
        if (!is_dir($backupDir)) mkdir($backupDir, 0777, true);

        $db = \App\Core\Database::getInstance();
        $docCount = $db->query("SELECT COUNT(*) as count FROM documents")->fetch()['count'] ?? 0;

        $filename = 'deped_dts_' . date('Y_m_d_His') . '_' . $docCount . '.sql';
        $filePath = $backupDir . '/' . $filename;
        $zipPath = $backupDir . '/' . $filename . '.zip';

        // Dump DB using MYSQL_PWD environment variable to prevent password disclosure warnings on stderr
        $envPrefix = $dbConfig['password'] !== '' ? "MYSQL_PWD=" . escapeshellarg($dbConfig['password']) . " " : "";
        $command = "{$envPrefix}mysqldump --single-transaction --quick -h {$host} -u {$user} {$dbname} > " . escapeshellarg($filePath) . " 2>&1";
        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new \Exception("Database backup failed: " . implode("\n", $output));
        }

        // Zip it
        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE) === TRUE) {
            $zip->addFile($filePath, $filename);
            $zip->close();
        }

        // Cleanup sql file
        if (file_exists($filePath)) unlink($filePath);
    }
}
