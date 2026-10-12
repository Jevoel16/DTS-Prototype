<?php

namespace App\Core;

/**
 * Utility class providing cryptographic key helper routines and security token sanitization.
 */
class SecurityHelper
{
    /** Minimum length for a newly created signing PIN. */
    public const MIN_PIN_LENGTH = 8;

    /** Format tag for keys sealed with Argon2id + XSalsa20-Poly1305 (sodium_crypto_secretbox). */
    private const KEY_FORMAT_SECRETBOX = "\x02";

    /**
     * Normalizes and validates user-provided PIN input prior to Ed25519 digital signing.
     *
     * Ensures empty or stale frontend fallback tokens are caught early, protecting against
     * unauthenticated key decryption attempts.
     */
    public static function resolvePin(?string $submittedPin): ?string
    {
        // Intercepts legacy 'CACHED_PIN' sentinel values left over from early session-caching builds to enforce explicit PIN re-entry
        if ($submittedPin === 'CACHED_PIN' || empty($submittedPin)) {
            return null;
        }

        return $submittedPin;
    }

    /**
     * Encrypt an Ed25519 secret key with a PIN-derived key (Argon2id) using authenticated encryption.
     * Returns base64(format tag | salt | nonce | ciphertext+MAC).
     */
    public static function encryptPrivateKey(string $secretKey, string $pin): string
    {
        $salt = random_bytes(SODIUM_CRYPTO_PWHASH_SALTBYTES);
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $key = self::derivePinKey($pin, $salt);

        $box = sodium_crypto_secretbox($secretKey, $nonce, $key);
        sodium_memzero($key);

        return base64_encode(self::KEY_FORMAT_SECRETBOX . $salt . $nonce . $box);
    }

    /**
     * Decrypt a stored private key with the PIN. Supports the current secretbox format and the two
     * legacy AES-256-CBC formats. Returns the 64-byte secret key, or null for a wrong PIN / bad data.
     */
    public static function decryptPrivateKey(string $storedKey, string $pin): ?string
    {
        $decoded = base64_decode($storedKey, true);
        if ($decoded === false) {
            return null;
        }

        $secretboxLength = 1 + SODIUM_CRYPTO_PWHASH_SALTBYTES + SODIUM_CRYPTO_SECRETBOX_NONCEBYTES
            + SODIUM_CRYPTO_SIGN_SECRETKEYBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES;

        if (strlen($decoded) === $secretboxLength && $decoded[0] === self::KEY_FORMAT_SECRETBOX) {
            $salt = substr($decoded, 1, SODIUM_CRYPTO_PWHASH_SALTBYTES);
            $nonce = substr($decoded, 1 + SODIUM_CRYPTO_PWHASH_SALTBYTES, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $box = substr($decoded, 1 + SODIUM_CRYPTO_PWHASH_SALTBYTES + SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $key = self::derivePinKey($pin, $salt);
            $secret = sodium_crypto_secretbox_open($box, $nonce, $key);
            sodium_memzero($key);
        } elseif (strlen($decoded) === 112) {
            // Legacy: Argon2id-derived key, AES-256-CBC without a MAC
            $salt = substr($decoded, 0, SODIUM_CRYPTO_PWHASH_SALTBYTES);
            $iv = substr($decoded, SODIUM_CRYPTO_PWHASH_SALTBYTES, 16);
            $key = self::derivePinKey($pin, $salt);
            $secret = openssl_decrypt(substr($decoded, SODIUM_CRYPTO_PWHASH_SALTBYTES + 16), 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        } elseif (strlen($decoded) === 64) {
            // Legacy: unsalted SHA-256(PIN) key with a fixed IV
            $secret = openssl_decrypt($decoded, 'aes-256-cbc', substr(hash('sha256', $pin), 0, 32), OPENSSL_RAW_DATA, str_repeat('0', 16));
        } else {
            return null;
        }

        return ($secret !== false && strlen($secret) === SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) ? $secret : null;
    }

    /**
     * True when a stored key uses a legacy format and should be re-encrypted after a successful unlock.
     */
    public static function isLegacyKeyFormat(string $storedKey): bool
    {
        $decoded = base64_decode($storedKey, true);
        return $decoded === false || $decoded === '' || $decoded[0] !== self::KEY_FORMAT_SECRETBOX;
    }

    private static function derivePinKey(string $pin, string $salt): string
    {
        return sodium_crypto_pwhash(
            SODIUM_CRYPTO_SECRETBOX_KEYBYTES,
            $pin,
            $salt,
            SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,
            SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE,
            SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13
        );
    }
}
