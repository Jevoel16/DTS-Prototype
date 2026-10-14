<?php

namespace App\Jobs;

class CreateBackupJob
{
    /**
     * Queue and cache tables are left out of backups: restoring them would bring back
     * jobs that were mid-run when the backup was taken (stuck forever as "reserved"),
     * stale rate-limit counters and cache rows. The live copies are kept on restore.
     */
    private const EXCLUDED_TABLES = ['jobs', 'rate_limits', 'cache'];

    public function __construct(private string $label = '')
    {
    }

    public function handle(): void
    {
        self::create($this->label);
    }

    /**
     * Dump the database to an (encrypted) zip in storage/app/backups and return its file name,
     * e.g. deped_dts_2026_10_14_230808_71.sql.zip or deped_dts_pre-restore_2026_..._71.sql.zip.
     */
    public static function create(string $label = ''): string
    {
        ini_set('memory_limit', '2G');
        set_time_limit(1200);

        $backupDir = BASE_PATH . '/storage/app/backups';
        if (!is_dir($backupDir)) mkdir($backupDir, 0770, true);

        $config = require BASE_PATH . '/src/Config/config.php';
        $db = \App\Core\Database::getInstance();
        $docCount = $db->query("SELECT COUNT(*) as count FROM documents")->fetch()['count'] ?? 0;

        // The real database name is part of the file name, so a test-database backup is never
        // mistaken for a production one
        $dbName = preg_replace('/[^A-Za-z0-9_]/', '_', (string) $config['database']['dbname']);
        $label = preg_replace('/[^A-Za-z0-9_-]/', '', $label);
        $filename = $dbName . '_' . ($label !== '' ? $label . '_' : '') . date('Y_m_d_His') . '_' . $docCount . '.sql';
        $filePath = $backupDir . '/' . $filename;
        $zipPath = $backupDir . '/' . $filename . '.zip';

        $ignore = array_map(fn ($table) => '--ignore-table=' . $config['database']['dbname'] . '.' . $table, self::EXCLUDED_TABLES);
        try {
            MysqlCli::run('mysqldump', ['--single-transaction', '--quick', ...$ignore], null, $filePath);
        } catch (\Throwable $e) {
            @unlink($filePath);
            throw new \Exception("Database backup failed: " . $e->getMessage());
        }

        // Zip it, AES-256 encrypted when BACKUP_PASSWORD is set (dumps hold password hashes, keys and guest data)
        $password = $config['app']['backup_password'];
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

        self::applyRetention($backupDir, (int) $config['app']['backup_keep']);

        return $filename . '.zip';
    }

    /**
     * Keep only the newest $keep backups (BACKUP_KEEP in .env; 0 keeps everything).
     */
    private static function applyRetention(string $backupDir, int $keep): void
    {
        if ($keep <= 0) {
            return;
        }
        $files = glob($backupDir . '/*.zip') ?: [];
        usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a));
        foreach (array_slice($files, $keep) as $old) {
            if (@unlink($old)) {
                \App\Core\SystemAudit::record('backup_pruned', ['file' => basename($old), 'keep' => $keep]);
            }
        }
    }
}
