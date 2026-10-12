<?php

namespace App\Services;

use App\Core\Database;
use App\Core\IntegrityManager;
use App\Policies\DocumentPolicy;
use App\Models\User;
use Exception;

class DocumentWorkflowService
{
    private Database $db;
    private DocumentPolicy $policy;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->policy = new DocumentPolicy();
    }

    /**
     * Safely executes an action within a DB Transaction using Optimistic Locking (via version increment).
     * 
     * Centralizes transaction management, error handling, and the "Active Guard" integrity checks.
     * Failing the policy check or encountering a concurrent modification will safely rollback.
     */
    private function executeInTransaction(string $idField, $idValue, ?User $user, ?string $policyMethod, callable $action)
    {
        $conn = $this->db->getConnection();
        
        try {
            $conn->beginTransaction();
            
            $stmt = $this->db->query("SELECT * FROM documents WHERE $idField = :id", [':id' => $idValue]);
            $document = $stmt->fetch();
            
            if (!$document) {
                throw new Exception("Document not found.");
            }
            
            // Active Guard / Authorization Check: Verify the user has permission AND the document hasn't been tampered with
            if ($user && $policyMethod) {
                if (!$this->policy->$policyMethod($user, $document)) {
                    // Commit early here: if the policy failed due to tampering, the Active Guard might have logged an Auto-Freeze event
                    $conn->commit();
                    throw new Exception("Action Denied: Document failed integrity check or you lack permissions.");
                }
            }
            
            // Execute Business Logic: the callable runs with the verified document row
            $result = $action($document, $this->db);
            
            // 4. Commit successful state transition
            $conn->commit();
            return $result;
            
        } catch (\Throwable $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Submit a new document (Guest).
     * 
     * Handles public form submissions. Initiates the cryptographic hash chain and handles dynamic purpose creation securely.
     */
    public function submitDocument(array $data): array
    {
        $conn = $this->db->getConnection();
        
        $maxAttempts = 3;
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            try {
                $conn->beginTransaction();
                
                $finalPurposeId = $data['purpose_id'];

                // Prevent DB bloating/DoS: map custom guest purposes to a generic 'Others' record instead of creating new rows indefinitely
                if ($data['purpose_id'] == '0') {
                    $otherPurposeText = "Others";
                    $stmt = $this->db->query("SELECT id FROM purposes WHERE name = :name AND is_official = 0", [':name' => $otherPurposeText]);
                    $existing = $stmt->fetch();

                    if ($existing) {
                        $finalPurposeId = $existing['id'];
                    } else {
                        $this->db->query("INSERT INTO purposes (name, is_official, requirements, suggested_route) VALUES (:name, 0, '[]', '[]')", [
                            ':name' => $otherPurposeText
                        ]);
                        $finalPurposeId = $conn->lastInsertId();
                    }

                    // Append the specific text to the document's title to preserve the guest's input without bloating the purposes table
                    $specificPurpose = trim($data['other_purpose_text'] ?? '');
                    if ($specificPurpose !== '') {
                        $data['title'] = $data['title'] . " (Purpose: " . substr($specificPurpose, 0, 100) . ")";
                    }
                }

                // Generate a highly randomized 10-character tracking code
                $trackingCode = 'DEPED-' . strtoupper(substr(sha1(uniqid('', true) . microtime()), 0, 10));

                $guestInfo = json_encode([
                    'name' => $data['guest_name'],
                    'email' => $data['guest_email'],
                    'phone' => $data['guest_phone']
                ]);

                $this->db->query("INSERT INTO documents (tracking_code, title, guest_info, district, department, purpose_id, status, current_step, created_at, updated_at) VALUES (:tracking_code, :title, :guest_info, :district, :department, :purpose_id, 'pending', 0, NOW(), NOW())", [
                    ':tracking_code' => $trackingCode,
                    ':title' => $data['title'],
                    ':guest_info' => $guestInfo,
                    ':district' => $data['district'],
                    ':department' => $data['department'],
                    ':purpose_id' => $finalPurposeId
                ]);
                
                $documentId = $conn->lastInsertId();

                $documentData = [
                    'tracking_code' => $trackingCode,
                    'title' => $data['title'],
                    'guest_info' => $guestInfo,
                    'district' => $data['district'],
                    'department' => $data['department'],
                    'purpose_id' => $finalPurposeId,
                    'finalized_route' => ''
                ];

                // Initialize the cryptographic hash chain with the first 'Submitted' log entry
                IntegrityManager::createLog($documentId, null, 'Submitted', 'Document submitted by guest via the public portal.', $documentData);

                $conn->commit();
                return ['tracking_code' => $trackingCode, 'document_id' => $documentId];
                
            } catch (\PDOException $e) {
                if ($conn->inTransaction()) {
                    $conn->rollBack();
                }
                
                // Check for MySQL Duplicate Entry Error (1062) instead of generic 23000
                // This prevents infinite loops on Foreign Key violations (like an invalid purpose_id).
                $isDuplicateEntry = isset($e->errorInfo[1]) && $e->errorInfo[1] == 1062;
                
                if ($isDuplicateEntry && $attempt < $maxAttempts - 1) {
                    $attempt++;
                    continue; // Try again with a new random tracking code
                }
                
                // If it's a different database error, or we ran out of attempts, throw it.
                throw $e;
            } catch (\Throwable $e) {
                if ($conn->inTransaction()) {
                    $conn->rollBack();
                }
                throw $e;
            }
        }
    }

    /**
     * Finalize document routing (Intake).
     * 
     * Called by the Records Office to accept a pending document and establish its definitive departmental route.
     * Also updates machine-learning heuristics for "suggested routing".
     */
    public function finalizeIntake(int $documentId, array $routeNames, User $officer, string $pin): void
    {
        $this->executeInTransaction('id', $documentId, $officer, 'manage', function ($document, $db) use ($routeNames, $officer, $pin) {
            if ($document['status'] !== 'pending') {
                throw new Exception("This document is already being processed.");
            }

            $firstDepartmentName = $routeNames[0];
            $stmt = $db->query("SELECT id FROM departments WHERE name = :name", [':name' => $firstDepartmentName]);
            $firstDepartment = $stmt->fetch();

            $finalizedRoute = array_map(function ($name) {
                return ['name' => $name, 'type' => 'initial'];
            }, $routeNames);

            $finalizedRouteJson = json_encode($finalizedRoute);

            // Fetch purpose to update suggested route learning
            $stmt = $db->query("SELECT is_official, suggested_route FROM purposes WHERE id = :id", [':id' => $document['purpose_id']]);
            $purpose = $stmt->fetch();

            if ($purpose && $purpose['suggested_route'] !== json_encode($routeNames)) {
                $learnedRoute = $routeNames;
                
                // If it's an official purpose, strip out the guest department if it's the first step 
                // so we don't permanently learn a specific guest's unit.
                if ($purpose['is_official'] && !empty($document['department'])) {
                    if (!empty($learnedRoute) && $learnedRoute[0] === $document['department']) {
                        array_shift($learnedRoute);
                    }
                }
                
                if ($purpose['suggested_route'] !== json_encode($learnedRoute)) {
                    $db->query("UPDATE purposes SET suggested_route = :route WHERE id = :id", [
                        ':route' => json_encode($learnedRoute),
                        ':id' => $document['purpose_id']
                    ]);
                }
            }

            // Optimistic locking check: increment version and update status. 
            // If rowCount() is 0, another process modified the row between our initial SELECT and this UPDATE.
            $stmt = $db->query("UPDATE documents SET status = 'in_transit', finalized_route = :route, current_step = 1, current_department_id = :dept_id, updated_at = NOW(), version = version + 1 WHERE id = :id AND version = :version", [
                ':route' => $finalizedRouteJson,
                ':dept_id' => $firstDepartment ? $firstDepartment['id'] : null,
                ':id' => $document['id'],
                ':version' => $document['version']
            ]);
            if ($stmt->rowCount() === 0) {
                throw new Exception("This document was just modified by another user. Please refresh.");
            }

            // Re-fetch document for hashing
            $updatedDocument = $db->query("SELECT * FROM documents WHERE id = :id", [':id' => $document['id']])->fetch();

            IntegrityManager::createLog(
                $document['id'],
                $officer->id,
                'Accepted and Document Routing finalized',
                "Route finalized. In transit to {$firstDepartmentName}.",
                $updatedDocument,
                $pin
            );
        });
    }

    /**
     * Scan document (Receive).
     * 
     * Handles the physical receipt of a document via QR code scan. Verifies that the scanning user's
     * department matches the expected next hop in the finalized route.
     */
    public function scanDocument(string $trackingCode, User $user, string $pin): string
    {
        return $this->executeInTransaction('tracking_code', $trackingCode, $user, 'process', function ($document, $db) use ($user, $pin) {
            $route = $document['finalized_route'] ? json_decode($document['finalized_route'], true) : [];
            $currentStepIndex = ((int)$document['current_step']) - 1;

            if ($document['status'] === 'in_transit') {
                if ($currentStepIndex >= count($route)) {
                    // The document has finished its route; only the Records Unit can do the final receive for release
                    $stmt = $db->query("SELECT name FROM departments WHERE id = :id", [':id' => $user->department_id]);
                    $dept = $stmt->fetch();
                    if ($dept && $dept['name'] === 'Records Unit') {
                        $stmt = $db->query("UPDATE documents SET status = 'ready_for_release', current_department_id = :dept_id, updated_at = NOW(), version = version + 1 WHERE id = :id AND version = :version", [
                            ':dept_id' => $user->department_id,
                            ':id' => $document['id'],
                            ':version' => $document['version']
                        ]);
                        if ($stmt->rowCount() === 0) throw new Exception("This document was just modified by another user. Please refresh.");
                        $updatedDoc = $db->query("SELECT * FROM documents WHERE id = :id", [':id' => $document['id']])->fetch();
                        IntegrityManager::createLog($document['id'], $user->id, 'Ready for Releasing', 'All processing steps completed. Document received by Records Unit for final releasing.', $updatedDoc, $pin);
                        return 'releasing';
                    }
                } else {
                    // Verify the scanning user belongs to the department responsible for the current step
                    $responsibleDepartmentName = $route[$currentStepIndex]['name'];
                    $stmt = $db->query("SELECT name FROM departments WHERE id = :id", [':id' => $user->department_id]);
                    $dept = $stmt->fetch();
                    
                    if ($dept && $dept['name'] === $responsibleDepartmentName) {
                        $stmt = $db->query("UPDATE documents SET status = 'processing', current_department_id = :dept_id, updated_at = NOW(), version = version + 1 WHERE id = :id AND version = :version", [
                            ':dept_id' => $user->department_id,
                            ':id' => $document['id'],
                            ':version' => $document['version']
                        ]);
                        if ($stmt->rowCount() === 0) throw new Exception("This document was just modified by another user. Please refresh.");
                        $updatedDoc = $db->query("SELECT * FROM documents WHERE id = :id", [':id' => $document['id']])->fetch();
                        IntegrityManager::createLog($document['id'], $user->id, 'Received', "Document received by {$dept['name']}.", $updatedDoc, $pin);
                        return ($user->role === 'officer') ? 'officer.tasks' : 'staff.tasks';
                    }
                }
                $responsibleDepartmentName = ($currentStepIndex >= count($route)) ? 'the Records Unit' : ($route[$currentStepIndex]['name'] ?? 'an unknown department');
                throw new Exception("This document is not for your department. It is waiting to be received by {$responsibleDepartmentName}.");
            }

            // Document NOT in a receivable state
            switch ($document['status']) {
                case 'processing':
                    $responsibleDepartmentName = $route[$currentStepIndex]['name'] ?? 'an unknown department';
                    $stmt = $db->query("SELECT name FROM departments WHERE id = :id", [':id' => $user->department_id]);
                    $dept = $stmt->fetch();
                    if ($dept && $dept['name'] === $responsibleDepartmentName) {
                        throw new Exception("You are already processing this document.");
                    }
                    throw new Exception("This document is currently being processed by the {$responsibleDepartmentName}.");
                case 'pending':
                    throw new Exception("This document is still pending intake by the Records Office and cannot be received yet.");
                case 'ready_for_release':
                    throw new Exception("This document is already in the 'Awaiting Release' list.");
                case 'completed':
                case 'declined':
                    throw new Exception("This document has already been released, please check your tracking code again.");
                default:
                    throw new Exception("This document cannot be received at this time. Its current status is: " . ucfirst($document['status']));
            }
        });
    }

    /**
     * Complete a processing task.
     * 
     * Marks the current department's action as finished, advances the step counter,
     * and places the document back in transit to the next department.
     */
    public function completeTask(int $documentId, string $remarks, User $user, string $pin): void
    {
        $this->executeInTransaction('id', $documentId, $user, 'process', function ($document, $db) use ($remarks, $user, $pin) {
            if ($document['status'] !== 'processing') {
                throw new Exception("This document is not currently being processed.");
            }

            $route = json_decode($document['finalized_route'], true) ?? [];
            $currentStepIndex = ((int)$document['current_step']) - 1;
            $currentDepartmentName = $route[$currentStepIndex]['name'] ?? 'Unknown';

            $newStep = $document['current_step'] + 1;
            
            $nextDepartmentId = null;
            if ($newStep <= count($route)) {
                $nextDepartmentName = $route[$newStep - 1]['name'];
                $stmt = $db->query("SELECT id FROM departments WHERE name = :name", [':name' => $nextDepartmentName]);
                $nextDept = $stmt->fetch();
                if ($nextDept) {
                    $nextDepartmentId = $nextDept['id'];
                }
            }

            // Atomically advance the step counter and set the next responsible department
            $stmt = $db->query("UPDATE documents SET status = 'in_transit', current_step = :step, current_department_id = :dept_id, updated_at = NOW(), version = version + 1 WHERE id = :id AND version = :version", [
                ':step' => $newStep,
                ':dept_id' => $nextDepartmentId,
                ':id' => $document['id'],
                ':version' => $document['version']
            ]);
            if ($stmt->rowCount() === 0) throw new Exception("This document was just modified by another user. Please refresh.");

            $updatedDoc = $db->query("SELECT * FROM documents WHERE id = :id", [':id' => $document['id']])->fetch();

            $logRemarks = $remarks ?: "Step processed by {$currentDepartmentName}.";
            if ($newStep > count($route)) {
                $logRemarks .= " In transit to Records Unit for releasing.";
            } else {
                $nextDeptName = $route[$newStep - 1]['name'];
                $logRemarks .= " In transit to {$nextDeptName}.";
            }

            IntegrityManager::createLog($document['id'], $user->id, 'Processing Complete', $logRemarks, $updatedDoc, $pin);
        });
    }

    /**
     * Release a document to the client.
     * 
     * Finalizes the workflow, removing the current department hold and permanently completing the transaction.
     */
    public function releaseDocument(int $documentId, User $officer, string $pin): void
    {
        $this->executeInTransaction('id', $documentId, $officer, 'manage', function ($document, $db) use ($officer, $pin) {
            if ($document['status'] !== 'ready_for_release') {
                throw new Exception("This document is not ready for release.");
            }

            $stmt = $db->query("UPDATE documents SET status = 'completed', current_department_id = NULL, released_at = NOW(), released_by_user_id = :uid, updated_at = NOW(), version = version + 1 WHERE id = :id AND version = :version", [
                ':uid' => $officer->id,
                ':id' => $document['id'],
                ':version' => $document['version']
            ]);
            if ($stmt->rowCount() === 0) throw new Exception("This document was just modified by another user. Please refresh.");

            $updatedDoc = $db->query("SELECT * FROM documents WHERE id = :id", [':id' => $document['id']])->fetch();

            IntegrityManager::createLog($document['id'], $officer->id, 'Document Released', 'The document has been released to the client.', $updatedDoc, $pin);
        });
    }

    /**
     * Decline an intake document.
     * 
     * Rejects a pending document before routing begins, permanently halting its progression.
     */
    public function declineDocument(int $documentId, string $reason, User $officer, string $pin): void
    {
        $this->executeInTransaction('id', $documentId, $officer, 'manage', function ($document, $db) use ($reason, $officer, $pin) {
            if ($document['status'] !== 'pending') {
                throw new Exception("This document cannot be declined as it is already being processed.");
            }

            $stmt = $db->query("UPDATE documents SET status = 'declined', decline_reason = :reason, declined_at = NOW(), updated_at = NOW(), version = version + 1 WHERE id = :id AND version = :version", [
                ':reason' => $reason,
                ':id' => $document['id'],
                ':version' => $document['version']
            ]);
            if ($stmt->rowCount() === 0) throw new Exception("This document was just modified by another user. Please refresh.");

            $updatedDoc = $db->query("SELECT * FROM documents WHERE id = :id", [':id' => $document['id']])->fetch();

            IntegrityManager::createLog($document['id'], $officer->id, 'Document Declined', $reason, $updatedDoc, $pin);
        });
    }



    /**
     * Halts all operations on a document. 
     * Typically triggered automatically by the Active Guard when tampering is detected, or manually by an admin.
     */
    public function freezeDocument(string $trackingCode, User $admin, string $pin): void
    {
        $this->executeInTransaction('tracking_code', $trackingCode, $admin, 'freeze', function ($document, $db) use ($admin, $pin) {
            $stmt = $db->query("UPDATE documents SET status = 'frozen', updated_at = NOW(), version = version + 1 WHERE id = :id AND version = :version", [':id' => $document['id'], ':version' => $document['version']]);
            if ($stmt->rowCount() === 0) throw new Exception("This document was just modified by another user. Please refresh.");
            $updatedDoc = $db->query("SELECT * FROM documents WHERE id = :id", [':id' => $document['id']])->fetch();
            IntegrityManager::createLog($document['id'], $admin->id, 'ADMIN: Document frozen.', 'An administrator has frozen this document, likely pending an integrity investigation.', $updatedDoc, $pin);
        });
    }

    /**
     * Restores a frozen document to its last logical status state based on the cryptographic log.
     */
    public function unfreezeDocument(string $trackingCode, User $admin, string $pin): string
    {
        return $this->executeInTransaction('tracking_code', $trackingCode, $admin, 'unfreeze', function ($document, $db) use ($admin, $pin) {
            $lastValidLog = $db->query("SELECT action FROM document_logs WHERE document_id = :id AND action != 'ADMIN: Document frozen.' AND action != 'System Auto-Freeze' ORDER BY id DESC LIMIT 1", [':id' => $document['id']])->fetch();
            
            $previousStatus = $document['status'];
            if ($lastValidLog) {
                $previousStatus = match($lastValidLog['action']) {
                    'Submitted' => 'pending',
                    'Accepted and Document Routing finalized' => 'in_transit',
                    'Received' => 'processing',
                    'Processing Complete' => 'in_transit',
                    'Ready for Releasing' => 'ready_for_release',
                    'Document Released' => 'completed',
                    default => 'processing'
                };
            }

            $stmt = $db->query("UPDATE documents SET status = :status, updated_at = NOW(), version = version + 1 WHERE id = :id AND version = :version", [
                ':status' => $previousStatus,
                ':id' => $document['id'],
                ':version' => $document['version']
            ]);
            if ($stmt->rowCount() === 0) throw new Exception("This document was just modified by another user. Please refresh.");

            $updatedDoc = $db->query("SELECT * FROM documents WHERE id = :id", [':id' => $document['id']])->fetch();
            IntegrityManager::createLog($document['id'], $admin->id, 'ADMIN: Document unfrozen.', "An administrator has unfrozen this document, restoring its status to " . ucfirst($previousStatus) . ".", $updatedDoc, $pin);
            return $previousStatus;
        });
    }

    /**
     * Fully recovers a tampered document by applying the last known-good data snapshot from the hash chain.
     */
    public function autoResolveDocument(string $trackingCode, User $admin, string $pin): string
    {
        return $this->executeInTransaction('tracking_code', $trackingCode, $admin, 'unfreeze', function ($document, $db) use ($admin, $pin) {
            // Tampered documents might not be in 'frozen' status if they were just flagged by verification
            // and haven't triggered the Active Guard yet.

            // Find the most recent uncorrupted log entry that contains a full data snapshot
            $stmt = $db->query("SELECT * FROM document_logs WHERE document_id = :id AND action != 'ADMIN: Document frozen.' AND action != 'System Auto-Freeze' AND document_snapshot IS NOT NULL ORDER BY id DESC LIMIT 1", [':id' => $document['id']]);
            $lastValidLog = $stmt->fetch();
            
            if (!$lastValidLog || !$lastValidLog['document_snapshot']) {
                throw new Exception("No valid document snapshot found to recover from.");
            }

            $snapshot = json_decode($lastValidLog['document_snapshot'], true);
            if (!$snapshot) {
                throw new Exception("Document snapshot is corrupted or invalid.");
            }

            $previousStatus = match($lastValidLog['action']) {
                'Submitted' => 'pending',
                'Accepted and Document Routing finalized' => 'in_transit',
                'Received' => 'processing',
                'Processing Complete' => 'in_transit',
                'Ready for Releasing' => 'ready_for_release',
                'Document Released' => 'completed',
                default => 'processing'
            };

            // Overwrite the tampered row with the safe snapshot data, incrementing version to lock the recovery
            $stmt = $db->query("UPDATE documents SET 
                title = :title, 
                guest_info = :guest_info, 
                district = :district, 
                department = :department, 
                purpose_id = :purpose_id, 
                finalized_route = :finalized_route, 
                status = :status, 
                updated_at = NOW(),
                version = version + 1
                WHERE id = :id AND version = :version", [
                ':title' => $snapshot['title'] ?? null,
                ':guest_info' => empty($snapshot['guest_info']) ? null : (is_array($snapshot['guest_info']) ? json_encode($snapshot['guest_info']) : $snapshot['guest_info']),
                ':district' => $snapshot['district'] ?? null,
                ':department' => $snapshot['department'] ?? null,
                ':purpose_id' => $snapshot['purpose_id'] ?? null,
                ':finalized_route' => empty($snapshot['finalized_route']) ? null : (is_array($snapshot['finalized_route']) ? json_encode($snapshot['finalized_route']) : $snapshot['finalized_route']),
                ':status' => $previousStatus,
                ':id' => $document['id'],
                ':version' => $document['version']
            ]);
            if ($stmt->rowCount() === 0) throw new Exception("This document was just modified by another user. Please refresh.");

            $updatedDoc = $db->query("SELECT * FROM documents WHERE id = :id", [':id' => $document['id']])->fetch();
            IntegrityManager::createLog($document['id'], $admin->id, 'ADMIN: Document Unfrozen & Restored', "An administrator has executed Auto-resolve, restoring the document from its last valid snapshot and returning status to " . ucfirst($previousStatus) . ".", $updatedDoc, $pin);
            
            return $previousStatus;
        });
    }
}
