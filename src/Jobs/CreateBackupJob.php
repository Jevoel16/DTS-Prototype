<?php

namespace App\Jobs;

class CreateBackupJob
{
    public function handle(): void
    {
        ini_set('memory_limit', '2G');
        set_time_limit(1200);

        $backupDir = BASE_PATH . '/storage/app/backups';
        if (!is_dir($backupDir)) mkdir($backupDir, 0777, true);

        $db = \App\Core\Database::getInstance();
        $docCount = $db->query("SELECT COUNT(*) as count FROM documents")->fetch()['count'] ?? 0;

        $filename = 'deped_dts_' . date('Y_m_d_His') . '_' . $docCount . '.sql';
        $filePath = $backupDir . '/' . $filename;
        $zipPath = $backupDir . '/' . $filename . '.zip';

        try {
            MysqlCli::run('mysqldump', ['--single-transaction', '--quick'], null, $filePath);
        } catch (\Throwable $e) {
            @unlink($filePath);
            throw new \Exception("Database backup failed: " . $e->getMessage());
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
