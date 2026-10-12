<?php

namespace App\Jobs;

class RestoreBackupJob
{
    protected $fileName;

    public function __construct(string $fileName)
    {
        $this->fileName = $fileName;
    }

    public function handle(): void
    {
        ini_set('memory_limit', '2G');
        set_time_limit(1200);

        $backupDir = BASE_PATH . '/storage/app/backups';
        $zipPath = $backupDir . '/' . $this->fileName;

        if (!file_exists($zipPath)) {
            return;
        }

        $config = require BASE_PATH . '/src/Config/config.php';
        $dbConfig = $config['database'];
        $host = escapeshellarg($dbConfig['host']);
        $user = escapeshellarg($dbConfig['user']);
        $pass = escapeshellarg($dbConfig['password']);
        $dbname = escapeshellarg($dbConfig['dbname']);

        // Extract
        $zip = new \ZipArchive();
        $sqlFile = null;
        if ($zip->open($zipPath) === TRUE) {
            $sqlFile = $zip->getNameIndex(0);
            $zip->extractTo($backupDir);
            $zip->close();
        }

        if ($sqlFile && file_exists($backupDir . '/' . $sqlFile)) {
            $filePath = $backupDir . '/' . $sqlFile;
            
            // Restore DB using MYSQL_PWD environment variable to suppress password warnings
            $envPrefix = $dbConfig['password'] !== '' ? "MYSQL_PWD=" . escapeshellarg($dbConfig['password']) . " " : "";
            $command = "{$envPrefix}mysql -h {$host} -u {$user} {$dbname} < " . escapeshellarg($filePath) . " 2>&1";
            exec($command, $output, $returnCode);

            if ($returnCode !== 0) {
                throw new \Exception("Database restore failed: " . implode("\n", $output));
            }
            
            // Cleanup extracted SQL file
            unlink($filePath);
        }
    }
}
