<?php

namespace App\Jobs;

use App\Core\Database;
use App\Events\IntegrityCheckFailed;
use App\Core\EventDispatcher;

class IntegrityCheckJob
{
    // Maximum number of mismatch IDs to store in-memory and persist to the results JSON.
    // The audit still COUNTS all mismatches accurately beyond this cap; only the stored list is bounded.
    private const MAX_TRACKED_MISMATCHES = 1000;

    protected $integrityCheckId;
    protected $publicKeyHistoryCache = [];

    public function __construct(string $integrityCheckId)
    {
        $this->integrityCheckId = $integrityCheckId;
    }

    // Executes a full cryptographic audit of the system. Verifies the SHA-256 hash chain, 
    // Ed25519 signatures against historical public keys, and live document states.
    public function handle(): void
    {
        date_default_timezone_set('Asia/Manila');
        $db = Database::getInstance();
        
        $db->query("UPDATE integrity_checks SET status = 'processing', progress = 5, updated_at = NOW() WHERE id = :id", [
            'id' => $this->integrityCheckId
        ]);

        try {
            $totalLogs = $db->query("SELECT COUNT(*) as c FROM document_logs")->fetch()['c'] ?? 0;
            $totalDocuments = $db->query("SELECT COUNT(*) as c FROM documents")->fetch()['c'] ?? 0;
            
            $invalidLogsCount = 0;
            $invalidSignaturesCount = 0;
            // Associative array used as a hashset for O(1) duplicate detection (replaces O(n) in_array calls).
            $mismatchedIdsSet = [];
            // Capped list stored for admin display — prevents a massive JSON blob from being written to the DB.
            $mismatchedIdsList = [];
            // True total mismatch count, accurate even when mismatches exceed MAX_TRACKED_MISMATCHES.
            $mismatchedIdsCount = 0;
            $liveStateErrorsCount = 0;
            $mismatchedDocumentTrackingCodes = [];
            
            $processedDocs = 0;
            $processedLogs = 0;

            if ($totalDocuments > 0) {
                $lastDocId = 0;
                $docChunkSize = 2000;
                
                while (true) {
                    $docsStmt = $db->query("SELECT * FROM documents WHERE id > :last_id ORDER BY id ASC LIMIT " . $docChunkSize, ['last_id' => $lastDocId]);
                    $docsChunk = $docsStmt->fetchAll();
                    
                    if (empty($docsChunk)) {
                        break;
                    }
                    
                    $docIds = array_column($docsChunk, 'id');
                    $placeholders = implode(',', array_fill(0, count($docIds), '?'));
                    
                    // Fetch all logs for this chunk of documents
                    $logsStmt = $db->query("SELECT dl.*, u.public_key, u.security_key_set_at FROM document_logs dl LEFT JOIN users u ON dl.user_id = u.id WHERE dl.document_id IN ($placeholders) ORDER BY dl.document_id ASC, dl.id ASC", $docIds);
                    $logsChunk = $logsStmt->fetchAll();

                    // Group logs by document ID
                    $logsByDoc = [];
                    foreach ($logsChunk as $log) {
                        $logsByDoc[$log['document_id']][] = $log;
                    }
                    
                    foreach ($docsChunk as $documentData) {
                        $docId = $documentData['id'];
                        $lastDocId = $docId;
                        
                        if ($processedDocs % 500 === 0) {
                            $statusCheck = $db->query("SELECT status FROM integrity_checks WHERE id = :id", ['id' => $this->integrityCheckId])->fetch();
                            if ($statusCheck && $statusCheck['status'] === 'cancelled') {
                                throw new \Exception("Job cancelled by user.");
                            }
                        }

                        $docLogs = $logsByDoc[$docId] ?? [];
                        $expectedPreviousHash = 'genesis_hash';
                        $latestStateHash = null;

                        foreach ($docLogs as $log) {
                            $timestampForHashing = date('c', strtotime($log['created_at']));
                            
                            $dataToHash = [
                                (int) $log['document_id'],
                                $log['user_id'] ? (int) $log['user_id'] : '',
                                $log['action'],
                                $timestampForHashing,
                                $expectedPreviousHash,
                                $log['document_state_hash'],
                                $log['signature']
                            ];
                            $recalculatedHash = hash('sha256', json_encode($dataToHash, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

                            if ($recalculatedHash !== $log['hash']) {
                                $invalidLogsCount++;
                                if (!isset($mismatchedIdsSet[$log['id']])) {
                                    $mismatchedIdsSet[$log['id']] = true;
                                    $mismatchedIdsCount++;
                                    if ($mismatchedIdsCount <= self::MAX_TRACKED_MISMATCHES) {
                                        $mismatchedIdsList[] = $log['id'];
                                    }
                                }
                            }

                            // Verify Cryptographic Signature
                            if ($log['signature'] && strlen($log['signature']) > 10) {
                                $isMockSignature = str_starts_with(base64_decode($log['signature']), 'SYSTEM_SIG:');
                                if ($isMockSignature) {
                                    $decodedMock = base64_decode($log['signature']);
                                    $expectedMock = "SYSTEM_SIG:{$log['action']}|{$log['document_state_hash']}";
                                     if ($decodedMock !== $expectedMock) {
                                        $invalidSignaturesCount++;
                                        if (!isset($mismatchedIdsSet[$log['id']])) {
                                            $mismatchedIdsSet[$log['id']] = true;
                                            $mismatchedIdsCount++;
                                            if ($mismatchedIdsCount <= self::MAX_TRACKED_MISMATCHES) {
                                                $mismatchedIdsList[] = $log['id'];
                                            }
                                        }
                                    }
                                } else {
                                    $pubKey = $this->getPublicKeyAtTime($db, $log['user_id'], $log['created_at'], $log['public_key'], $log['security_key_set_at']);
                                    if ($pubKey) {
                                        $signedData = $log['action'] . '|' . $log['document_state_hash'];
                                        $rawPubKey = base64_decode($pubKey);
                                        
                                        if (strlen($rawPubKey) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
                                            $invalidSignaturesCount++;
                                            if (!isset($mismatchedIdsSet[$log['id']])) {
                                                $mismatchedIdsSet[$log['id']] = true;
                                                $mismatchedIdsCount++;
                                                if ($mismatchedIdsCount <= self::MAX_TRACKED_MISMATCHES) {
                                                    $mismatchedIdsList[] = $log['id'];
                                                }
                                            }
                                        } else {
                                            $verified = sodium_crypto_sign_verify_detached(
                                                base64_decode($log['signature']),
                                                $signedData,
                                                $rawPubKey
                                            );
                                            if (!$verified) {
                                                $invalidSignaturesCount++;
                                                if (!isset($mismatchedIdsSet[$log['id']])) {
                                                    $mismatchedIdsSet[$log['id']] = true;
                                                    $mismatchedIdsCount++;
                                                    if ($mismatchedIdsCount <= self::MAX_TRACKED_MISMATCHES) {
                                                        $mismatchedIdsList[] = $log['id'];
                                                    }
                                                }
                                            }
                                        }
                                    } else {
                                        $invalidSignaturesCount++;
                                        if (!isset($mismatchedIdsSet[$log['id']])) {
                                            $mismatchedIdsSet[$log['id']] = true;
                                            $mismatchedIdsCount++;
                                            if ($mismatchedIdsCount <= self::MAX_TRACKED_MISMATCHES) {
                                                $mismatchedIdsList[] = $log['id'];
                                            }
                                        }
                                    }
                                }
                            }

                            $expectedPreviousHash = $log['hash'];
                            $latestStateHash = $log['document_state_hash'];
                            $processedLogs++;
                        }

                        // Step 3: Compare the current live state against its last cryptographically sealed state
                        if ($latestStateHash) {
                            $currentStateHash = \App\Core\IntegrityManager::calculateStateHash($documentData);
                            
                            if ($currentStateHash !== $latestStateHash) {
                                // Fallback: Check JSON spacing artifact
                                $altDocumentData = $documentData;
                                if (is_string($altDocumentData['finalized_route'])) {
                                    $decoded = json_decode($altDocumentData['finalized_route'], true);
                                    if ($decoded !== null) {
                                        $altDocumentData['finalized_route'] = json_encode($decoded);
                                        $altStateHash = \App\Core\IntegrityManager::calculateStateHash($altDocumentData);
                                        if ($altStateHash === $latestStateHash) {
                                            $currentStateHash = $altStateHash;
                                        }
                                    }
                                }
                            }

                            if ($currentStateHash !== $latestStateHash) {
                                $liveStateErrorsCount++;
                                // Cap stored tracking codes to avoid an unbounded list in the results JSON
                                if (count($mismatchedDocumentTrackingCodes) < self::MAX_TRACKED_MISMATCHES) {
                                    $mismatchedDocumentTrackingCodes[] = $documentData['tracking_code'];
                                }
                                $documentObj = \App\Models\Document::findById($documentData['id']);
                                EventDispatcher::dispatch(new IntegrityCheckFailed($documentObj, 'Verification Scan'));
                            }
                        }
                        
                        $processedDocs++;
                        if ($processedDocs % 100 == 0) {
                            $percent = 5 + floor(($processedDocs / $totalDocuments) * 90);
                            $db->query("UPDATE integrity_checks SET progress = :p, updated_at = NOW() WHERE id = :id", [
                                'p' => $percent,
                                'id' => $this->integrityCheckId
                            ]);
                        }
                    } // End foreach docs
                } // End while true
            } // End if totalDocuments > 0

            // Use $mismatchedIdsCount (the true total) so the percentage stays accurate even when the stored list is capped
            $verifiedPercentage = ($totalLogs > 0) ? (($totalLogs - $mismatchedIdsCount) / $totalLogs) * 100 : 100;

            $results = [
                'verified_percentage' => round($verifiedPercentage, 2),
                'last_checked' => date('Y-m-d H:i:s'),
                'total_logs' => $totalLogs,
                'invalid_logs' => $invalidLogsCount,
                'invalid_signatures' => $invalidSignaturesCount,
                'mismatched_ids' => $mismatchedIdsList,
                'total_mismatched_ids' => $mismatchedIdsCount,
                'live_state_errors_count' => $liveStateErrorsCount,
                'mismatched_document_tracking_codes' => $mismatchedDocumentTrackingCodes,
            ];

            if (!is_dir(BASE_PATH . '/cache')) {
                mkdir(BASE_PATH . '/cache', 0777, true);
            }
            file_put_contents(BASE_PATH . '/cache/integrity-check-result.json', json_encode($results));

            $db->query("UPDATE integrity_checks SET status = 'completed', progress = 100, results = :res, updated_at = NOW() WHERE id = :id", [
                'res' => json_encode($results),
                'id' => $this->integrityCheckId
            ]);

        } catch (\Throwable $e) {
            $db->query("UPDATE integrity_checks SET status = 'failed', error_message = :err, updated_at = NOW() WHERE id = :id", [
                'err' => $e->getMessage(),
                'id' => $this->integrityCheckId
            ]);
        }
    }

    protected function getPublicKeyAtTime($db, $userId, $timestamp, $userPubKey, $userKeySetAt)
    {
        if (!array_key_exists($userId, $this->publicKeyHistoryCache)) {
            $this->publicKeyHistoryCache[$userId] = $db->query("SELECT public_key, activated_at, deactivated_at FROM user_public_key_histories WHERE user_id = :uid", ['uid' => $userId])->fetchAll();
        }

        $timestampTime = strtotime($timestamp);
        foreach ($this->publicKeyHistoryCache[$userId] as $history) {
            $activatedAt = strtotime($history['activated_at']);
            $deactivatedAt = $history['deactivated_at'] ? strtotime($history['deactivated_at']) : null;
            if ($activatedAt <= $timestampTime && ($deactivatedAt === null || $deactivatedAt >= $timestampTime)) {
                return $history['public_key'];
            }
        }

        if ($userPubKey) {
            $hasHistory = !empty($this->publicKeyHistoryCache[$userId]);
            if (!$hasHistory) return $userPubKey;
            if ($userKeySetAt && strtotime($userKeySetAt) <= strtotime($timestamp)) return $userPubKey;
        }

        return null;
    }
}
