<?php

namespace App\Controllers;

use App\Core\Database;

class SecurityKeyController
{
    public function store()
    {
        $pin = $_POST['pin'] ?? '';
        $pinConfirm = $_POST['pin_confirm'] ?? '';
        
        if (strlen($pin) < 6) {
            $_SESSION['error'] = "PIN must be at least 6 characters.";
            $this->redirectBasedOnRole();
        }

        if ($pin !== $pinConfirm) {
            $_SESSION['error'] = "PIN and Confirm PIN do not match.";
            $this->redirectBasedOnRole();
        }

        $db = Database::getInstance();
        $userId = $_SESSION['user_id'];
        
        $keypair = sodium_crypto_sign_keypair();
        $secretKey = sodium_crypto_sign_secretkey($keypair);
        $publicKey = sodium_crypto_sign_publickey($keypair);

        // Encrypt the private key with the PIN using AES-256-CBC
        $salt = random_bytes(SODIUM_CRYPTO_PWHASH_SALTBYTES);
        $key = sodium_crypto_pwhash(
            32, 
            $pin, 
            $salt, 
            SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE, 
            SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE, 
            SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13
        );
        $iv = random_bytes(16);
        $encryptedPriv = openssl_encrypt($secretKey, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);

        $pubB64 = base64_encode($publicKey);
        $privB64 = base64_encode($salt . $iv . $encryptedPriv);
        
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

        // Fix: Update the session with the new private key so client-side JS can use it without requiring re-login
        $_SESSION['private_key'] = $privB64;

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
