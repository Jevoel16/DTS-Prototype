<?php

namespace App\Controllers;

use App\Core\Database;

class BackupManagerController
{
    public function index()
    {
        $backupDir = BASE_PATH . '/storage/app/backups';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0777, true);
        }

        $files = array_diff(scandir($backupDir), ['.', '..']);
        $backups = [];

        $search = $_GET['search'] ?? null;

        foreach ($files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'zip') {
                $filePath = $backupDir . '/' . $file;
                $lastModified = date('M d, Y, h:i A', filemtime($filePath));
                
                if ($search && !str_contains(strtolower($file), strtolower($search)) && !str_contains(strtolower($lastModified), strtolower($search))) {
                    continue;
                }

                $backups[] = [
                    'file_path' => $filePath,
                    'file_name' => $file,
                    'file_size' => $this->humanReadableSize(filesize($filePath)),
                    'last_modified_raw' => filemtime($filePath),
                    'last_modified' => $lastModified,
                ];
            }
        }

        usort($backups, function ($a, $b) {
            return $b['last_modified_raw'] <=> $a['last_modified_raw'];
        });

        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode($backups);
            exit;
        }

        require BASE_PATH . '/src/Views/admin/backups.php';
    }

    public function create()
    {
        $db = Database::getInstance();
        $payload = json_encode(['class' => \App\Jobs\CreateBackupJob::class, 'data' => []]);
        $db->query("INSERT INTO jobs (queue, payload, attempts, available_at, created_at) VALUES ('default', :payload, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP())", [
            'payload' => $payload
        ]);

        $_SESSION['success'] = 'Backup has been queued and will be created in the background.';
        header('Location: /system/backups');
        exit;
    }

    public function download($fileName)
    {
        $safeFileName = basename($fileName);
        $filePath = BASE_PATH . '/storage/app/backups/' . $safeFileName;

        if (file_exists($filePath)) {
            header('Content-Description: File Transfer');
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="'.basename($filePath).'"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($filePath));
            readfile($filePath);
            exit;
        }

        header('Location: /system/backups');
        exit;
    }

    public function delete($fileName)
    {
        $safeFileName = basename($fileName);
        $filePath = BASE_PATH . '/storage/app/backups/' . $safeFileName;

        if (file_exists($filePath)) {
            unlink($filePath);
        }

        header('Location: /system/backups');
        exit;
    }

    public function restore($fileName)
    {
        $db = Database::getInstance();
        $safeFileName = basename($fileName);
        $payload = json_encode(['class' => \App\Jobs\RestoreBackupJob::class, 'data' => [$safeFileName]]);
        $db->query("INSERT INTO jobs (queue, payload, attempts, available_at, created_at) VALUES ('default', :payload, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP())", [
            'payload' => $payload
        ]);

        $_SESSION['success'] = 'Restore job has been queued and will be processed in the background.';
        header('Location: /system/backups');
        exit;
    }

    private function humanReadableSize($bytes, $decimals = 2) {
        $size = array('B','kB','MB','GB','TB','PB','EB','ZB','YB');
        $factor = floor((strlen($bytes) - 1) / 3);
        return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . @$size[$factor];
    }
}
