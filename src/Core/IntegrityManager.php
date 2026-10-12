<?php

namespace App\Core;

class IntegrityManager
{
    /**
     * Calculate a SHA-256 hash representing the current state of a document.
     * This protects against tampering with document metadata (title, submitter, etc.).
     *
     * @param array $document An associative array containing document data
     * @return string
     */
    /**
     * Calculate a SHA-256 hash representing the current state of a document.
     * 
     * Normalizes critical document fields into a consistent array structure to compute a tamper-evident SHA-256 state hash.
     */
    public static function calculateStateHash(array $document): string
    {
        // Normalize nulls to empty strings to maintain consistent hash outputs across DB dialects
        $trackingCode = $document['tracking_code'] ?? '';
        $title = $document['title'] ?? '';
        
        // Parse JSON guest info deterministically, falling back to empty fields if corrupted
        $guestInfo = ['name' => '', 'email' => '', 'phone' => ''];
        if (!empty($document['guest_info'])) {
            $parsedInfo = json_decode($document['guest_info'], true);
            if (is_array($parsedInfo)) {
                $guestInfo = array_merge($guestInfo, $parsedInfo);
            }
        }
        
        $district = $document['district'] ?? '';
        $department = $document['department'] ?? '';
        $purposeId = (string) ($document['purpose_id'] ?? '');
        
        $finalizedRoute = $document['finalized_route'] ?? '';
        if (is_array($finalizedRoute)) {
            $finalizedRoute = json_encode($finalizedRoute);
        }

        $stateData = [
            $trackingCode,
            $title,
            $guestInfo['name'],
            $guestInfo['email'],
            $guestInfo['phone'],
            $district,
            $department,
            $purposeId,
            $finalizedRoute
        ];
        return hash('sha256', json_encode($stateData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * Generate an Ed25519 signature for the given action and document state using Sodium.
     * 
     * Retrieves and decrypts the user's private key using their PIN to generate a non-repudiable 
     * signature over the combined action and state.
     *
     * @param int|null $userId
     * @param string $pin
     * @param string $actionText
     * @param string $stateHash
     * @return string
     * @throws \Exception
     */
    public static function signAction(?int $userId, ?string $pin, string $actionText, string $stateHash): string
    {
        // Allow system-level actions (where userId is null) to bypass user PIN checks.
        // If a user ID is present, a PIN MUST be provided.
        if ($userId === null) {
            return base64_encode("SYSTEM_SIG:{$actionText}|{$stateHash}");
        }
        if (empty($pin)) {
            throw new \Exception("Action Denied: Security PIN is required to digitally sign this action.");
        }

        $db = Database::getInstance();
        $user = $db->query("SELECT private_key FROM users WHERE id = :id", ['id' => $userId])->fetch();

        if (!$user || !$user['private_key']) {
            throw new \Exception("User does not have a digital signature set up.");
        }

        $decoded = base64_decode($user['private_key']);
        
        // Support both legacy AES-only format and the new Argon2-based key derivation format for backwards compatibility
        if (strlen($decoded) === 64) {
            $key = substr(hash('sha256', $pin), 0, 32);
            $iv = str_repeat('0', 16);
            $encryptedPriv = $decoded;
        } elseif (strlen($decoded) === 112) {
            $salt = substr($decoded, 0, SODIUM_CRYPTO_PWHASH_SALTBYTES);
            $iv = substr($decoded, SODIUM_CRYPTO_PWHASH_SALTBYTES, 16);
            $encryptedPriv = substr($decoded, SODIUM_CRYPTO_PWHASH_SALTBYTES + 16);
            
            $key = sodium_crypto_pwhash(
                32, 
                $pin, 
                $salt, 
                SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE, 
                SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE, 
                SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13
            );
        } else {
            throw new \Exception("Invalid private key format.");
        }

        $decryptedPriv = openssl_decrypt($encryptedPriv, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);

        if ($decryptedPriv === false || strlen($decryptedPriv) !== 64) {
            throw new \Exception("Invalid Security PIN or corrupted key.");
        }

        // Generate a detached signature binding the action directly to the current state hash
        $signature = sodium_crypto_sign_detached($actionText . '|' . $stateHash, $decryptedPriv);
        return base64_encode($signature);
    }

    /**
     * Create a cryptographic log entry for a document action.
     * 
     * Appends a new cryptographic block to the document's log chain, sealing the current state, 
     * action, and signature together with the previous block's hash.
     *
     * @param int $documentId
     * @param int|null $userId
     * @param string $action
     * @param string $remarks
     * @param array $documentData The current state of the document (used for state hash)
     * @param string $pin The user's PIN for signature (can be empty for system actions)
     * @param string|null $overrideStateHash Optional state hash override for auto-freezing
     * @return int The ID of the newly created log entry
     */
    public static function createLog(int $documentId, ?int $userId, string $action, string $remarks, array $documentData, string $pin = '', ?string $overrideStateHash = null): int
    {
        $db = Database::getInstance();
        $createdAt = date('Y-m-d H:i:s');
        $timestampForHashing = date('c', strtotime($createdAt));

        // 1. Determine state hash; allow override for auto-freeze scenarios to preserve the last valid state
        $documentStateHash = $overrideStateHash ?? self::calculateStateHash($documentData);

        // 2. Generate the signature bonded to the action and state hash
        $signature = self::signAction($userId, $pin, $action, $documentStateHash);

        // 3. Fetch the last block's hash to maintain the unbreakable chain sequence
        $stmt = $db->query(
            "SELECT hash FROM document_logs WHERE document_id = :document_id ORDER BY id DESC LIMIT 1",
            [':document_id' => $documentId]
        );
        $lastLog = $stmt->fetch();
        $previousHash = $lastLog ? $lastLog['hash'] : 'genesis_hash';

        // 4. Compute the final block hash encompassing the entire payload and the parent hash
        $dataToHash = [
            $documentId,
            ($userId ?? ''),
            $action,
            $timestampForHashing,
            $previousHash,
            $documentStateHash,
            $signature
        ];
        $hash = hash('sha256', json_encode($dataToHash, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        // 5. Insert the log entry
        $db->query(
            "INSERT INTO document_logs 
            (document_id, user_id, action, remarks, previous_hash, hash, signature, document_state_hash, document_snapshot, created_at) 
            VALUES 
            (:document_id, :user_id, :action, :remarks, :previous_hash, :hash, :signature, :document_state_hash, :document_snapshot, :created_at)",
            [
                ':document_id' => $documentId,
                ':user_id' => $userId,
                ':action' => $action,
                ':remarks' => $remarks,
                ':previous_hash' => $previousHash,
                ':hash' => $hash,
                ':signature' => $signature,
                ':document_state_hash' => $documentStateHash,
                ':document_snapshot' => json_encode($documentData),
                ':created_at' => $createdAt
            ]
        );

        return (int) $db->getConnection()->lastInsertId();
    }

    /**
     * Verify the current live state of a document against its last cryptographic log.
     * 
     * Compares the live document row against the state sealed in its most recent cryptographic log 
     * to detect unauthorized database tampering.
     *
     * @param array $document
     * @return bool
     */
    public static function verifyCurrentState(array $document): bool
    {
        $db = Database::getInstance();
        $stmt = $db->query(
            "SELECT document_state_hash FROM document_logs WHERE document_id = :document_id ORDER BY id DESC LIMIT 1",
            [':document_id' => $document['id']]
        );
        $lastLog = $stmt->fetch();

        // If no logs exist yet, it's a new document, nothing to verify against.
        if (!$lastLog) {
            return true;
        }

        $currentStateHash = self::calculateStateHash($document);
        
        // Attempt a fallback JSON re-encoding to handle minor spacing differences between raw PDO and ORM outputs before declaring tampering
        if ($currentStateHash !== $lastLog['document_state_hash']) {
            if (isset($document['finalized_route']) && is_string($document['finalized_route'])) {
                $decoded = json_decode($document['finalized_route'], true);
                if ($decoded !== null) {
                    $altDocument = $document;
                    $altDocument['finalized_route'] = json_encode($decoded);
                    if (self::calculateStateHash($altDocument) === $lastLog['document_state_hash']) {
                        return true;
                    }
                }
            }
            return false; // Real tampering detected
        }

        return true;
    }

    /**
     * Automatically freeze a document due to an integrity mismatch.
     * 
     * Instantly halts workflow progression if a document fails the verifyCurrentState check, 
     * sealing the tampered state for admin review.
     *
     * @param array $document
     * @param string $reason
     * @return void
     */
    public static function autoFreeze(array $document, string $reason): void
    {
        if (($document['status'] ?? '') === 'frozen') {
            return;
        }

        $db = Database::getInstance();
        
        // Update document status to frozen
        $db->query("UPDATE documents SET status = 'frozen', updated_at = NOW() WHERE id = :id", [
            ':id' => $document['id']
        ]);

        // Retrieve the last known good state hash to embed in the freeze log, ensuring the anomaly remains flagged
        $stmt = $db->query(
            "SELECT document_state_hash FROM document_logs WHERE document_id = :id ORDER BY id DESC LIMIT 1",
            [':id' => $document['id']]
        );
        $lastLog = $stmt->fetch();
        $validStateHash = $lastLog ? $lastLog['document_state_hash'] : self::calculateStateHash($document);

        // Create the system log entry for the auto-freeze
        self::createLog(
            $document['id'],
            null, // System-initiated
            'System Auto-Freeze',
            "Document automatically frozen by the Trust Builder system due to an integrity mismatch detected during {$reason}.",
            $document,
            '', // No PIN
            $validStateHash
        );
    }
}
