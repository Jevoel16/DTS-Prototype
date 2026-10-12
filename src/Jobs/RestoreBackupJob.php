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

        // Extract
        $zip = new \ZipArchive();
        $sqlFile = null;
        if ($zip->open($zipPath) === TRUE) {
            $sqlFile = $zip->getNameIndex(0);
            // Only the expected dump file, never a path that could escape the backup folder
            if (!$sqlFile || basename($sqlFile) !== $sqlFile || !str_ends_with($sqlFile, '.sql')) {
                $zip->close();
                throw new \Exception("Backup archive has unexpected contents.");
            }
            $password = (require BASE_PATH . '/src/Config/config.php')['app']['backup_password'];
            if ($password !== '') {
                $zip->setPassword($password);
            }
            $extracted = $zip->extractTo($backupDir, $sqlFile);
            $zip->close();
            if (!$extracted) {
                throw new \Exception("Could not extract the backup. Is BACKUP_PASSWORD the one it was created with?");
            }
        }

        if ($sqlFile && file_exists($backupDir . '/' . $sqlFile)) {
            $filePath = $backupDir . '/' . $sqlFile;
            
            try {
                MysqlCli::run('mysql', [], $filePath);
            } catch (\Throwable $e) {
                throw new \Exception("Database restore failed: " . $e->getMessage());
            } finally {
                // Never leave a plain-text dump lying next to the zips
                @unlink($filePath);
            }
        }
    }
}
