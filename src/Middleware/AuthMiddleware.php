<?php

namespace App\Middleware;

use App\Core\Database;

/**
 * Authentication and Key Setup Guard Middleware.
 * 
 * Enforces session authentication and verifies that users have initialized their Ed25519 digital signature key pair
 * before allowing state-mutating requests.
 */
class AuthMiddleware
{
    /**
     * Inspects the active user session and key initialization status.
     */
    public function handle()
    {
        // Require active session — unauthenticated requests are redirected immediately to prevent unauthorized route execution
        if (!isset($_SESSION['user_id'])) {
            header("Location: /login");
            exit;
        }

        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        // Release the session write lock early ONLY for read-only API requests (e.g. chart/polling GET endpoints).
        // For state-mutating requests and web page renders, keep session write open so controllers can set
        // $_SESSION['success'], $_SESSION['error'], and $_SESSION['info'] flash messages.
        if ($method === 'GET' && str_starts_with($uri, '/api/')) {
            session_write_close();
        }
        
        // Enforce mandatory security key setup for all routes except the key setup page and logout endpoint
        if ($uri !== '/security-key' && $uri !== '/logout') {
            $db = Database::getInstance();
            $user = $db->query("SELECT security_key_set_at FROM users WHERE id = :id", ['id' => $_SESSION['user_id']])->fetch();
            
            // Check if user has initialized their cryptographic signing keys
            $needsKey = $user && is_null($user['security_key_set_at']);
            $_SESSION['needs_security_key'] = $needsKey;

            // Block POST operations if security key setup is missing to ensure no unsigned document transactions occur
            if ($needsKey && $method === 'POST') {
                $_SESSION['error'] = "You must set up your security key first.";
                header("Location: /");
                exit;
            }
        }
    }
}

