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
            $zip->extractTo($backupDir);
            $zip->close();
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
