<?php

namespace App\Core;

use PDO;

class NotificationService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Send a notification to an entire department
     */
    public function notifyDepartment(int $departmentId, string $title, string $message, string $type = 'info'): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO notifications (department_id, title, message, type)
            VALUES (:dept_id, :title, :message, :type)
        ");
        $stmt->execute([
            'dept_id' => $departmentId,
            'title' => $title,
            'message' => $message,
            'type' => $type
        ]);

        // Enforce max limit of 10 per department
        $this->db->prepare("
            DELETE FROM notifications 
            WHERE department_id = :dept_id1 
            AND id NOT IN (
                SELECT id FROM (
                    SELECT id FROM notifications 
                    WHERE department_id = :dept_id2 
                    ORDER BY created_at DESC 
                    LIMIT 10
                ) as latest
            )
        ")->execute([
            'dept_id1' => $departmentId,
            'dept_id2' => $departmentId
        ]);
    }

    /**
     * Send a notification to a specific user
     */
    public function notifyUser(int $userId, string $title, string $message, string $type = 'info'): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO notifications (user_id, title, message, type)
            VALUES (:user_id, :title, :message, :type)
        ");
        $stmt->execute([
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'type' => $type
        ]);

        // Enforce max limit of 10 per user
        $this->db->prepare("
            DELETE FROM notifications 
            WHERE user_id = :user_id1 
            AND id NOT IN (
                SELECT id FROM (
                    SELECT id FROM notifications 
                    WHERE user_id = :user_id2 
                    ORDER BY created_at DESC 
                    LIMIT 10
                ) as latest
            )
        ")->execute([
            'user_id1' => $userId,
            'user_id2' => $userId
        ]);
    }

    /**
     * Get unread notifications for the currently logged-in user
     */
    public function getUnreadForCurrentUser(): array
    {
        if (!isset($_SESSION['user_id'])) {
            return [];
        }

        $userId = $_SESSION['user_id'];
        $departmentId = $_SESSION['department_id'] ?? null;

        $query = "
            SELECT * FROM notifications 
            WHERE is_read = 0 
            AND (user_id = :user_id " . ($departmentId ? "OR department_id = :dept_id" : "") . ")
            ORDER BY created_at DESC
            LIMIT 10
        ";

        $stmt = $this->db->prepare($query);
        $params = ['user_id' => $userId];
        if ($departmentId) {
            $params['dept_id'] = $departmentId;
        }

        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Mark a notification as read (or all if ID is null)
     */
    public function markAsRead(?int $notificationId = null): void
    {
        if (!isset($_SESSION['user_id'])) {
            return;
        }

        $userId = $_SESSION['user_id'];
        $departmentId = $_SESSION['department_id'] ?? null;

        if ($notificationId) {
            // Mark specific
            $stmt = $this->db->prepare("
                UPDATE notifications 
                SET is_read = 1 
                WHERE id = :id 
                AND (user_id = :user_id " . ($departmentId ? "OR department_id = :dept_id" : "") . ")
            ");
            $params = ['id' => $notificationId, 'user_id' => $userId];
            if ($departmentId) {
                $params['dept_id'] = $departmentId;
            }
            $stmt->execute($params);
        } else {
            // Mark all
            $stmt = $this->db->prepare("
                UPDATE notifications 
                SET is_read = 1 
                WHERE is_read = 0 
                AND (user_id = :user_id " . ($departmentId ? "OR department_id = :dept_id" : "") . ")
            ");
            $params = ['user_id' => $userId];
            if ($departmentId) {
                $params['dept_id'] = $departmentId;
            }
            $stmt->execute($params);
        }
    }
}
