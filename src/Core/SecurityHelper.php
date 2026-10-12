<?php

namespace App\Core;

/**
 * Utility class providing cryptographic key helper routines and security token sanitization.
 */
class SecurityHelper
{
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
}

