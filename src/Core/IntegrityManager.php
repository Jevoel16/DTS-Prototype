<?php

namespace App\Core;

class IntegrityManager
{
    private const MAX_FAILED_PINS = 5;
    private const PIN_LOCKOUT_SECONDS = 900;

    /** Legacy, unkeyed marker used for system entries before system signatures were keyed. */
    private const SYSTEM_SIG_PREFIX = 'SYSTEM_SIG:';
    /** System entries signed with an HMAC under APP_INTEGRITY_KEY. */
    private const SYSTEM_HMAC_PREFIX = 'SYSTEM_HMAC:';

    /** Per-request cache of user_public_key_histories rows, keyed by user ID. */
    private static array $keyHistoryCache = [];

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
        // System actions (no user) are signed with the server's integrity key, so they cannot be
        // forged from the database alone. Without a key they fall back to the legacy plain marker.
        if ($userId === null) {
            $key = self::integrityKey();
            if ($key !== '') {
                return base64_encode(self::SYSTEM_HMAC_PREFIX . hash_hmac('sha256', "{$actionText}|{$stateHash}", $key));
            }
            return base64_encode(self::SYSTEM_SIG_PREFIX . "{$actionText}|{$stateHash}");
        }
        // If a user ID is present, a PIN MUST be provided.
        if (empty($pin)) {
            throw new \Exception("Action Denied: Security PIN is required to digitally sign this action.");
        }

        // Lock signing after repeated wrong PINs. Checked before the Argon2 derivation so guessing
        // cannot also be used to exhaust server memory/CPU.
        $pinThrottleKey = "pin:user:{$userId}";
        if (RateLimiter::tooMany($pinThrottleKey, self::MAX_FAILED_PINS)) {
            $minutes = (int) ceil(RateLimiter::availableIn($pinThrottleKey) / 60);
            throw new \Exception("Too many incorrect PIN attempts. Signing is locked for {$minutes} minute(s).");
        }

        $db = Database::getInstance();
        $user = $db->query("SELECT private_key FROM users WHERE id = :id", ['id' => $userId])->fetch();

        if (!$user || !$user['private_key']) {
            throw new \Exception("User does not have a digital signature set up.");
        }

        // Handles the current authenticated format and both legacy AES-CBC formats
        $decryptedPriv = SecurityHelper::decryptPrivateKey($user['private_key'], $pin);

        if ($decryptedPriv === null) {
            // Recorded on a separate connection, so the caller's transaction rollback does not erase it
            RateLimiter::hit($pinThrottleKey, self::MAX_FAILED_PINS, self::PIN_LOCKOUT_SECONDS);
            throw new \Exception("Invalid Security PIN or corrupted key.");
        }
        RateLimiter::clear($pinThrottleKey);

        // Upgrade legacy key encryption now that the PIN is known (same key pair, so signatures are unaffected)
        if (SecurityHelper::isLegacyKeyFormat($user['private_key'])) {
            $db->query("UPDATE users SET private_key = :priv WHERE id = :id", [
                'priv' => SecurityHelper::encryptPrivateKey($decryptedPriv, $pin),
                'id' => $userId
            ]);
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
     * @param bool $resealChain Update the document's chain seal (false only for automatic freezes)
     * @return int The ID of the newly created log entry
     */
    public static function createLog(int $documentId, ?int $userId, string $action, string $remarks, array $documentData, string $pin = '', ?string $overrideStateHash = null, bool $resealChain = true): int
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

        // 5. Insert the log entry, sealed with the server-side key so the chain cannot be recomputed from the DB alone
        $db->query(
            "INSERT INTO document_logs 
            (document_id, user_id, action, remarks, previous_hash, hash, seal, signature, document_state_hash, document_snapshot, created_at)
            VALUES 
            (:document_id, :user_id, :action, :remarks, :previous_hash, :hash, :seal, :signature, :document_state_hash, :document_snapshot, :created_at)",
            [
                ':document_id' => $documentId,
                ':user_id' => $userId,
                ':action' => $action,
                ':remarks' => $remarks,
                ':previous_hash' => $previousHash,
                ':hash' => $hash,
                ':seal' => self::sealFor($hash),
                ':signature' => $signature,
                ':document_state_hash' => $documentStateHash,
                ':document_snapshot' => json_encode($documentData),
                ':created_at' => $createdAt
            ]
        );
        $logId = (int) $db->getConnection()->lastInsertId();

        // Re-seal the document's chain head so its workflow state and log count are bound to this entry.
        // An automatic freeze does not re-seal: the tampered state must stay flagged until an
        // administrator unfreezes or auto-resolves the document (both of which re-seal).
        if ($resealChain) {
            self::refreshChainSeal($documentId);
        }

        return $logId;
    }

    /**
     * Keyed seal over a document's workflow state and the head of its log chain.
     *
     * The state hash in each log covers the document's metadata only. This seal adds what the
     * chain alone cannot prove: the live workflow fields (status, step, holder, decline reason,
     * releaser) and which entry is the newest and how many there are, so changing a document's
     * status or deleting its newest log entries is detected. Null when no integrity key is set.
     */
    public static function chainSealFor(array $document, int $lastLogId, string $lastLogHash, int $logCount): ?string
    {
        $key = self::integrityKey();
        if ($key === '') {
            return null;
        }
        $data = json_encode([
            (int) $document['id'],
            (string) ($document['status'] ?? ''),
            (string) ($document['current_step'] ?? ''),
            (string) ($document['current_department_id'] ?? ''),
            (string) ($document['decline_reason'] ?? ''),
            (string) ($document['released_by_user_id'] ?? ''),
            $lastLogId,
            $lastLogHash,
            $logCount,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return hash_hmac('sha256', $data, $key);
    }

    /**
     * Recompute and store the chain seal from the document's current row and logs.
     * Call only after a verified state change (createLog does this for every entry).
     */
    public static function refreshChainSeal(int $documentId): void
    {
        if (self::integrityKey() === '') {
            return;
        }
        $db = Database::getInstance();
        $document = $db->query("SELECT * FROM documents WHERE id = :id", [':id' => $documentId])->fetch();
        $head = self::chainHead($documentId);
        if (!$document || $head === null) {
            return;
        }
        $db->query("UPDATE documents SET chain_seal = :seal WHERE id = :id", [
            ':seal' => self::chainSealFor($document, $head['id'], $head['hash'], $head['count']),
            ':id' => $documentId,
        ]);
    }

    /**
     * Newest log entry and log count of a document, or null when it has no logs.
     *
     * @return array{id:int, hash:string, count:int}|null
     */
    private static function chainHead(int $documentId): ?array
    {
        $row = Database::getInstance()->query(
            "SELECT COUNT(*) AS c, MAX(id) AS last_id FROM document_logs WHERE document_id = :id",
            [':id' => $documentId]
        )->fetch();
        if (!$row || (int) $row['c'] === 0) {
            return null;
        }
        $hash = Database::getInstance()->query("SELECT hash FROM document_logs WHERE id = :id", [':id' => $row['last_id']])->fetch()['hash'] ?? '';
        return ['id' => (int) $row['last_id'], 'hash' => (string) $hash, 'count' => (int) $row['c']];
    }

    /**
     * Check the stored chain seal against the document row and the given chain head.
     * Always passes when no integrity key is configured.
     */
    public static function chainSealMatches(array $document, int $lastLogId, string $lastLogHash, int $logCount): bool
    {
        if (self::integrityKey() === '') {
            return true;
        }
        $expected = self::chainSealFor($document, $lastLogId, $lastLogHash, $logCount);
        return !empty($document['chain_seal']) && hash_equals($expected, $document['chain_seal']);
    }

    /**
     * True when the state hash of $document equals $expected. Retries once with the route
     * JSON re-encoded, since MySQL normalises JSON spacing differently from json_encode().
     */
    public static function stateHashMatches(array $document, ?string $expected): bool
    {
        if ($expected === null || $expected === '') {
            return false;
        }
        if (hash_equals($expected, self::calculateStateHash($document))) {
            return true;
        }
        if (isset($document['finalized_route']) && is_string($document['finalized_route'])) {
            $decoded = json_decode($document['finalized_route'], true);
            if ($decoded !== null) {
                $document['finalized_route'] = json_encode($decoded);
                return hash_equals($expected, self::calculateStateHash($document));
            }
        }
        return false;
    }

    /**
     * A log's snapshot can be trusted for recovery only when the log itself is intact (its block
     * hash recomputes and its seal is valid) and the snapshot hashes to the log's state hash.
     */
    public static function isTrustedSnapshot(array $log, array $snapshot): bool
    {
        return self::verifySeal($log)
            && hash_equals((string) $log['hash'], self::computeBlockHash($log, (string) ($log['previous_hash'] ?? 'genesis_hash')))
            && self::stateHashMatches($snapshot, $log['document_state_hash'] ?? null);
    }

    /**
     * Verify a log's signature: Ed25519 for user entries (against the key active at the time),
     * an HMAC for keyed system entries, or the exact legacy marker for older system entries.
     */
    public static function verifySignature(array $log): bool
    {
        $signature = (string) ($log['signature'] ?? '');
        if (strlen($signature) <= 10) {
            return false;
        }
        $signedText = $log['action'] . '|' . $log['document_state_hash'];
        $decoded = base64_decode($signature, true);

        if (empty($log['user_id'])) {
            if ($decoded !== false && str_starts_with($decoded, self::SYSTEM_HMAC_PREFIX)) {
                $key = self::integrityKey();
                return $key !== '' && hash_equals(hash_hmac('sha256', $signedText, $key), substr($decoded, strlen(self::SYSTEM_HMAC_PREFIX)));
            }
            return $decoded === self::SYSTEM_SIG_PREFIX . $signedText;
        }

        $publicKey = self::publicKeyAt((int) $log['user_id'], (string) $log['created_at']);
        $rawPublicKey = $publicKey ? base64_decode($publicKey, true) : false;
        if ($rawPublicKey === false || strlen($rawPublicKey) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES || $decoded === false
            || strlen($decoded) !== SODIUM_CRYPTO_SIGN_BYTES) {
            return false;
        }
        return sodium_crypto_sign_verify_detached($decoded, $signedText, $rawPublicKey);
    }

    /**
     * True for a legacy, unkeyed system signature (anyone with database access could write one).
     */
    public static function isLegacySystemSignature(array $log): bool
    {
        $decoded = base64_decode((string) ($log['signature'] ?? ''), true);
        return empty($log['user_id']) && $decoded !== false && str_starts_with($decoded, self::SYSTEM_SIG_PREFIX);
    }

    /**
     * The public key a user had at a given time, from their key history (falls back to the
     * current key for users whose history predates key-history tracking).
     */
    public static function publicKeyAt(int $userId, string $timestamp): ?string
    {
        $db = Database::getInstance();
        if (!array_key_exists($userId, self::$keyHistoryCache)) {
            self::$keyHistoryCache[$userId] = [
                'history' => $db->query("SELECT public_key, activated_at, deactivated_at FROM user_public_key_histories WHERE user_id = :uid", ['uid' => $userId])->fetchAll(),
                'user' => $db->query("SELECT public_key, security_key_set_at FROM users WHERE id = :uid", ['uid' => $userId])->fetch() ?: null,
            ];
        }
        $cached = self::$keyHistoryCache[$userId];

        $time = strtotime($timestamp);
        foreach ($cached['history'] as $history) {
            $activatedAt = strtotime($history['activated_at']);
            $deactivatedAt = $history['deactivated_at'] ? strtotime($history['deactivated_at']) : null;
            if ($activatedAt <= $time && ($deactivatedAt === null || $deactivatedAt >= $time)) {
                return $history['public_key'];
            }
        }

        $user = $cached['user'];
        if ($user && $user['public_key']) {
            if (empty($cached['history'])) {
                return $user['public_key'];
            }
            if ($user['security_key_set_at'] && strtotime($user['security_key_set_at']) <= $time) {
                return $user['public_key'];
            }
        }
        return null;
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
            "SELECT hash, seal, document_state_hash FROM document_logs WHERE document_id = :document_id ORDER BY id DESC LIMIT 1",
            [':document_id' => $document['id']]
        );
        $lastLog = $stmt->fetch();

        // If no logs exist yet, it's a new document, nothing to verify against.
        if (!$lastLog) {
            return true;
        }

        // A log appended or rewritten directly in the database cannot carry a valid seal
        if (!self::verifySeal($lastLog)) {
            return false;
        }

        // Metadata (title, submitter, route...) must match the newest sealed state hash
        if (!self::stateHashMatches($document, $lastLog['document_state_hash'])) {
            return false;
        }

        // Workflow state (status, step...) and the chain head (newest entry, entry count) must match
        // the keyed chain seal; this catches a changed status or deleted newest log entries
        $head = self::chainHead((int) $document['id']);
        return $head !== null && self::chainSealMatches($document, $head['id'], $head['hash'], $head['count']);
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
            $validStateHash,
            false // keep the evidence: do not bless the tampered workflow state
        );
    }

    /**
     * Server-side HMAC key from APP_INTEGRITY_KEY. Empty means sealing is disabled (legacy mode).
     */
    public static function integrityKey(): string
    {
        static $key = null;
        if ($key === null) {
            $key = (string) ((require BASE_PATH . '/src/Config/config.php')['app']['integrity_key'] ?? '');
        }
        return $key;
    }

    /**
     * Keyed seal over a log's block hash. Because the key never lives in the database, someone with
     * DB write access can recompute SHA-256 hashes but cannot produce matching seals.
     */
    public static function sealFor(?string $hash): ?string
    {
        $key = self::integrityKey();
        if ($key === '' || $hash === null || $hash === '') {
            return null;
        }
        return hash_hmac('sha256', $hash, $key);
    }

    /**
     * Check a log row's seal. Always passes when no key is configured.
     */
    public static function verifySeal(array $log): bool
    {
        if (self::integrityKey() === '') {
            return true;
        }
        $expected = self::sealFor($log['hash'] ?? null);
        return $expected !== null && !empty($log['seal']) && hash_equals($expected, $log['seal']);
    }

    /**
     * Recompute a log's block hash from its stored fields (same formula as createLog).
     */
    public static function computeBlockHash(array $log, string $previousHash): string
    {
        $dataToHash = [
            (int) $log['document_id'],
            $log['user_id'] ? (int) $log['user_id'] : '',
            $log['action'],
            date('c', strtotime($log['created_at'])),
            $previousHash,
            $log['document_state_hash'],
            $log['signature']
        ];
        return hash('sha256', json_encode($dataToHash, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * Seal every unsealed log whose document chain still verifies. Documents with a broken chain are
     * skipped (and returned) so tampered history is never sealed. Run once after APP_INTEGRITY_KEY is
     * set (composer run seal), and after seeding.
     *
     * @return array{sealed:int, skipped_documents:array<int>}
     */
    public static function sealPendingLogs(): array
    {
        if (self::integrityKey() === '') {
            throw new \Exception("APP_INTEGRITY_KEY is not set.");
        }

        $db = Database::getInstance();
        $conn = $db->getConnection();
        $sealed = 0;
        $skipped = [];
        $lastDocId = 0;

        while (true) {
            $docIds = $db->query(
                "SELECT DISTINCT document_id FROM document_logs WHERE seal IS NULL AND document_id > :last ORDER BY document_id LIMIT 500",
                [':last' => $lastDocId]
            )->fetchAll(\PDO::FETCH_COLUMN);
            if (empty($docIds)) {
                break;
            }
            $docIds = array_map('intval', $docIds);
            $lastDocId = end($docIds);

            $placeholders = implode(',', array_fill(0, count($docIds), '?'));
            $logs = $db->query(
                "SELECT id, document_id, user_id, action, created_at, hash, seal, document_state_hash, signature
                 FROM document_logs WHERE document_id IN ($placeholders) ORDER BY document_id, id",
                $docIds
            )->fetchAll();

            $byDoc = [];
            foreach ($logs as $log) {
                $byDoc[$log['document_id']][] = $log;
            }

            $conn->beginTransaction();
            $update = $conn->prepare("UPDATE document_logs SET seal = :seal WHERE id = :id");
            foreach ($byDoc as $docId => $docLogs) {
                $previousHash = 'genesis_hash';
                $chainOk = true;
                foreach ($docLogs as $log) {
                    $sealOk = $log['seal'] === null || self::verifySeal($log);
                    if (!$sealOk || $log['hash'] !== self::computeBlockHash($log, $previousHash)) {
                        $chainOk = false;
                        break;
                    }
                    $previousHash = $log['hash'];
                }
                if (!$chainOk) {
                    $skipped[] = (int) $docId;
                    continue;
                }
                foreach ($docLogs as $log) {
                    if ($log['seal'] === null) {
                        $update->execute([':seal' => self::sealFor($log['hash']), ':id' => $log['id']]);
                        $sealed++;
                    }
                }
            }
            $conn->commit();
        }

        $heads = self::sealPendingChainHeads($skipped);

        return [
            'sealed' => $sealed,
            'skipped_documents' => array_values(array_unique(array_merge($skipped, $heads['skipped_documents']))),
            'sealed_heads' => $heads['sealed'],
        ];
    }

    /**
     * Give every document without a chain seal one, but only when its whole chain verifies (block
     * hashes, seals) and its live metadata matches the newest entry. Run after upgrading, so
     * existing documents are covered; their workflow state at that moment becomes the baseline.
     *
     * @param int[] $alreadySkipped documents whose log chain already failed and must not be sealed
     * @return array{sealed:int, skipped_documents:array<int>}
     */
    private static function sealPendingChainHeads(array $alreadySkipped): array
    {
        $db = Database::getInstance();
        $sealed = 0;
        $skipped = [];
        $lastDocId = 0;
        $skipSet = array_flip($alreadySkipped);

        while (true) {
            $documents = $db->query(
                "SELECT * FROM documents WHERE chain_seal IS NULL AND id > :last ORDER BY id LIMIT 500",
                [':last' => $lastDocId]
            )->fetchAll();
            if (empty($documents)) {
                break;
            }
            $lastDocId = (int) end($documents)['id'];

            foreach ($documents as $document) {
                $docId = (int) $document['id'];
                if (isset($skipSet[$docId])) {
                    continue;
                }
                $logs = $db->query(
                    "SELECT id, document_id, user_id, action, created_at, hash, seal, document_state_hash, signature FROM document_logs WHERE document_id = :id ORDER BY id",
                    [':id' => $docId]
                )->fetchAll();

                $chainOk = !empty($logs);
                $previousHash = 'genesis_hash';
                foreach ($logs as $log) {
                    if (!self::verifySeal($log) || $log['hash'] !== self::computeBlockHash($log, $previousHash)) {
                        $chainOk = false;
                        break;
                    }
                    $previousHash = $log['hash'];
                }
                if (!$chainOk || !self::stateHashMatches($document, end($logs)['document_state_hash'])) {
                    $skipped[] = $docId;
                    continue;
                }

                self::refreshChainSeal($docId);
                $sealed++;
            }
        }

        return ['sealed' => $sealed, 'skipped_documents' => $skipped];
    }
}
