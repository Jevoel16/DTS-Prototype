<?php

namespace App\Controllers;

use App\Core\Database;
use App\Core\SecurityHelper;

class SecurityKeyController
{
    public function store()
    {
        $pin = $_POST['pin'] ?? '';
        $pinConfirm = $_POST['pin_confirm'] ?? '';
        
        $minLength = SecurityHelper::MIN_PIN_LENGTH;
        if (strlen($pin) < $minLength) {
            $_SESSION['error'] = "PIN must be at least {$minLength} characters.";
            $this->redirectBasedOnRole();
        }

        if ($pin !== $pinConfirm) {
            $_SESSION['error'] = "PIN and Confirm PIN do not match.";
            $this->redirectBasedOnRole();
        }

        $db = Database::getInstance();
        $userId = $_SESSION['user_id'];

        // An existing signing key can only be replaced after an admin resets it; otherwise a hijacked
        // session could swap in a key whose PIN the attacker knows and sign as this user.
        $existing = $db->query("SELECT security_key_set_at FROM users WHERE id = :id", ['id' => $userId])->fetch();
        if ($existing && $existing['security_key_set_at'] !== null) {
            $_SESSION['error'] = "Your digital signature is already set up. Ask an administrator to reset it if you need a new one.";
            $this->redirectBasedOnRole();
        }

        $keypair = sodium_crypto_sign_keypair();
        $secretKey = sodium_crypto_sign_secretkey($keypair);
        $publicKey = sodium_crypto_sign_publickey($keypair);

        // Encrypt the private key with a PIN-derived key (Argon2id + authenticated secretbox)
        $pubB64 = base64_encode($publicKey);
        $privB64 = SecurityHelper::encryptPrivateKey($secretKey, $pin);
        sodium_memzero($secretKey);
        
        $now = date('Y-m-d H:i:s');

        $db->query("UPDATE users SET public_key = :pub, private_key = :priv, security_key_set_at = :now WHERE id = :id", [
            'pub' => $pubB64,
            'priv' => $privB64,
            'now' => $now,
            'id' => $userId
        ]);

        $db->query("UPDATE user_public_key_histories SET deactivated_at = :now, updated_at = :now2 WHERE user_id = :id AND deactivated_at IS NULL", [
            'now' => $now,
            'now2' => $now,
            'id' => $userId
        ]);

        $db->query("INSERT INTO user_public_key_histories (user_id, public_key, activated_at, created_at, updated_at) VALUES (:user_id, :pub, :now, :now2, :now3)", [
            'user_id' => $userId,
            'pub' => $pubB64,
            'now' => $now,
            'now2' => $now,
            'now3' => $now
        ]);

        // A new key resets any PIN lockout from the old one
        \App\Core\RateLimiter::clear("pin:user:{$userId}");

        // Fix: Clear the user's personal page cache. If we don't, CacheMiddleware might serve an old cached version 
        // of the dashboard containing the setup modal, causing it to pop up infinitely.
        $prefix = "cache_user_{$userId}";
        $cacheDir = BASE_PATH . '/cache/responses/';
        if (is_dir($cacheDir)) {
            $files = glob($cacheDir . $prefix . '_*.html');
            if ($files) {
                foreach ($files as $file) {
                    if (is_file($file)) {
                        @unlink($file);
                    }
                }
            }
        }

        $_SESSION['success'] = "Digital Signature successfully generated and secured.";
        $this->redirectBasedOnRole();
    }

    private function redirectBasedOnRole()
    {
        $role = $_SESSION['role'] ?? 'staff';
        switch ($role) {
            case 'admin':
                header("Location: /admin-dashboard");
                break;
            case 'officer':
                header("Location: /intake");
                break;
            case 'staff':
            default:
                header("Location: /tasks");
                break;
        }
        exit;
    }
}
