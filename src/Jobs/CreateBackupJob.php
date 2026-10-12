<?php

namespace App\Jobs;

class CreateBackupJob
{
    public function handle(): void
    {
        ini_set('memory_limit', '2G');
        set_time_limit(1200);

        $backupDir = BASE_PATH . '/storage/app/backups';
        if (!is_dir($backupDir)) mkdir($backupDir, 0770, true);

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

        // Zip it, AES-256 encrypted when BACKUP_PASSWORD is set (dumps hold password hashes, keys and guest data)
        $password = (require BASE_PATH . '/src/Config/config.php')['app']['backup_password'];
        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE) === TRUE) {
            $zip->addFile($filePath, $filename);
            if ($password !== '') {
                $zip->setEncryptionName($filename, \ZipArchive::EM_AES_256, $password);
            } else {
                error_log("BACKUP_PASSWORD is not set: backup {$filename}.zip is stored unencrypted.");
            }
            $zip->close();
        }

        // Cleanup sql file
        if (file_exists($filePath)) unlink($filePath);
    }
}
