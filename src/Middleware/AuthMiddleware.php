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
    private const IDLE_TIMEOUT = 1800;      // 30 minutes without a page view
    private const ABSOLUTE_TIMEOUT = 28800; // 8 hours after sign-in

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
        $isApiPoll = $method === 'GET' && str_starts_with($uri, '/api/');

        // Idle and absolute session timeouts (sessions predating these fields start their clock now)
        $now = time();
        $_SESSION['login_at'] ??= $now;
        $_SESSION['last_activity'] ??= $now;
        if ($now - $_SESSION['last_activity'] > self::IDLE_TIMEOUT || $now - $_SESSION['login_at'] > self::ABSOLUTE_TIMEOUT) {
            $this->endSession("Your session has expired. Please sign in again.");
        }
        // Background polling (notifications, charts) must not keep an idle session alive
        if (!$isApiPoll) {
            $_SESSION['last_activity'] = $now;
        }

        // Re-read the account every request so deletions, role changes and key resets apply immediately
        $db = Database::getInstance();
        $user = $db->query("SELECT role, department_id, security_key_set_at FROM users WHERE id = :id", ['id' => $_SESSION['user_id']])->fetch();
        if (!$user) {
            $this->endSession("Your account is no longer active.");
        }
        $_SESSION['role'] = $user['role'];
        $_SESSION['department_id'] = $user['department_id'];

        // Release the session write lock early ONLY for read-only API requests (e.g. chart/polling GET endpoints).
        // For state-mutating requests and web page renders, keep session write open so controllers can set
        // $_SESSION['success'], $_SESSION['error'], and $_SESSION['info'] flash messages.
        if ($isApiPoll) {
            session_write_close();
        }

        // Enforce mandatory security key setup for all routes except the key setup page and logout endpoint
        if ($uri !== '/security-key' && $uri !== '/logout') {
            // Check if user has initialized their cryptographic signing keys
            $needsKey = is_null($user['security_key_set_at']);
            $_SESSION['needs_security_key'] = $needsKey;

            // Block POST operations if security key setup is missing to ensure no unsigned document transactions occur
            if ($needsKey && $method === 'POST') {
                $_SESSION['error'] = "You must set up your security key first.";
                header("Location: /");
                exit;
            }
        }
    }

    /**
     * Drop the authenticated session but keep a fresh, anonymous one for the flash message and CSRF token.
     */
    private function endSession(string $message): void
    {
        $_SESSION = [];
        session_regenerate_id(true);
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['error'] = $message;

        $isAjax = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
            || str_starts_with(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/', '/api/');
        if ($isAjax) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $message]);
            exit;
        }

        header("Location: /login");
        exit;
    }
}

