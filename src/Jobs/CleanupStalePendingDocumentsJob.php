<?php

namespace App\Jobs;

use App\Core\Database;
use App\Core\IntegrityManager;
use Throwable;

class CleanupStalePendingDocumentsJob
{
    /**
     * Executes the garbage collection process for stale pending documents.
     * 
     * Transitions documents that have been in 'pending' status for longer than the specified threshold 
     * to 'declined' (with reason "Pending Timeout"), sealing a system log entry to maintain cryptographic 
     * log continuity without destroying historical data.
     * 
     * @param int $expirationDays Threshold in days before a pending document expires (default: 3)
     * @return int Number of documents expired
     */
    public function handle(int $expirationDays = 3): int
    {
        $db = Database::getInstance();
        $conn = $db->getConnection();

        // Calculate cut-off timestamp based on the configured expiration days
        $thresholdDate = date('Y-m-d H:i:s', strtotime("-{$expirationDays} days"));
        
        $stmt = $db->query(
            "SELECT * FROM documents WHERE status = 'pending' AND created_at <= :threshold",
            [':threshold' => $thresholdDate]
        );
        $staleDocuments = $stmt->fetchAll();

        if (empty($staleDocuments)) {
            return 0;
        }

        $processedCount = 0;

        foreach ($staleDocuments as $document) {
            try {
                $conn->beginTransaction();

                $declineReason = "System Auto-Cleanup: Pending document expired after lingering for over {$expirationDays} days without Records Office intake.";

                // Transition status to 'declined' with optimistic locking version check
                $updateStmt = $db->query(
                    "UPDATE documents 
                     SET status = 'declined', 
                         decline_reason = :reason,
                         declined_at = NOW(),
                         updated_at = NOW(), 
                         version = version + 1 
                     WHERE id = :id AND version = :version AND status = 'pending'",
                    [
                        ':reason' => $declineReason,
                        ':id' => $document['id'],
                        ':version' => $document['version']
                    ]
                );

                if ($updateStmt->rowCount() === 0) {
                    // Row was updated by another process simultaneously; skip and rollback
                    $conn->rollBack();
                    continue;
                }

                // Re-fetch document state for snapshot hashing
                $updatedDoc = $db->query("SELECT * FROM documents WHERE id = :id", [':id' => $document['id']])->fetch();

                // Append system-sealed cryptographic log entry
                IntegrityManager::createLog(
                    (int) $document['id'],
                    null, // System-initiated action
                    'Document Declined',
                    $declineReason,
                    $updatedDoc
                );

                $conn->commit();
                $processedCount++;

            } catch (Throwable $e) {
                if ($conn->inTransaction()) {
                    $conn->rollBack();
                }
                error_log("Failed to expire stale document ID {$document['id']}: " . $e->getMessage());
            }
        }

        return $processedCount;
    }
}
