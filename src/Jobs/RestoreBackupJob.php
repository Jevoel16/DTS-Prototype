<?php

namespace App\Jobs;

use App\Core\Database;
use App\Core\NotificationService;
use App\Core\SystemAudit;

class RestoreBackupJob
{
    protected $fileName;
    protected $userId;

    public function __construct(string $fileName, ?int $userId = null)
    {
        $this->fileName = $fileName;
        $this->userId = $userId;
    }

    public function handle(): void
    {
        ini_set('memory_limit', '2G');
        set_time_limit(1200);

        $backupDir = BASE_PATH . '/storage/app/backups';
        $zipPath = $backupDir . '/' . $this->fileName;

        if (!file_exists($zipPath)) {
            SystemAudit::record('backup_restore_failed', ['file' => $this->fileName, 'user_id' => $this->userId, 'reason' => 'file not found']);
            return;
        }

        // A restore replaces every table, so first save what is about to be overwritten
        $safetyBackup = CreateBackupJob::create('pre-restore');
        $before = $this->counts();

        try {
            $this->import($backupDir, $zipPath);
        } catch (\Throwable $e) {
            SystemAudit::record('backup_restore_failed', [
                'file' => $this->fileName,
                'user_id' => $this->userId,
                'safety_backup' => $safetyBackup,
                'reason' => $e->getMessage(),
            ]);
            throw $e;
        }

        $after = $this->counts();

        // The database itself was just replaced, so the lasting record goes to the system audit file
        SystemAudit::record('backup_restored', [
            'file' => $this->fileName,
            'user_id' => $this->userId,
            'safety_backup' => $safetyBackup,
            'documents_before' => $before['documents'],
            'documents_after' => $after['documents'],
            'logs_before' => $before['logs'],
            'logs_after' => $after['logs'],
        ]);

        if ($this->userId) {
            $lost = max(0, $before['documents'] - $after['documents']);
            (new NotificationService())->notifyUser(
                $this->userId,
                'Backup restored',
                "Restored {$this->fileName}. Documents: {$before['documents']} before, {$after['documents']} after"
                    . ($lost ? " ({$lost} newer document(s) are no longer in the database)" : '')
                    . ". The previous state was saved as {$safetyBackup}.",
                $lost ? 'warning' : 'success'
            );
        }
    }

    /**
     * @return array{documents:int, logs:int}
     */
    private function counts(): array
    {
        $db = Database::getInstance();
        return [
            'documents' => (int) $db->query("SELECT COUNT(*) AS c FROM documents")->fetch()['c'],
            'logs' => (int) $db->query("SELECT COUNT(*) AS c FROM document_logs")->fetch()['c'],
        ];
    }

    private function import(string $backupDir, string $zipPath): void
    {
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
