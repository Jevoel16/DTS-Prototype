<?php

namespace App\Controllers;

use App\Core\Database;
use App\Core\RateLimiter;

/**
 * Self-service account settings for any signed-in user.
 */
class AccountController
{
    private const MIN_PASSWORD_LENGTH = 10;
    private const MAX_FAILED_ATTEMPTS = 5;
    private const LOCKOUT_SECONDS = 900;

    public function showPasswordForm()
    {
        $forced = !empty($_SESSION['must_change_password']);
        $minLength = self::MIN_PASSWORD_LENGTH;
        require BASE_PATH . '/src/Views/account/password.php';
    }

    public function updatePassword()
    {
        $userId = (int) $_SESSION['user_id'];
        $current = (string) ($_POST['current_password'] ?? '');
        $new = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['new_password_confirmation'] ?? '');

        // The current password is re-checked, so a hijacked session cannot lock the owner out
        $throttleKey = "password-change:user:{$userId}";
        if (RateLimiter::tooMany($throttleKey, self::MAX_FAILED_ATTEMPTS)) {
            $minutes = (int) ceil(RateLimiter::availableIn($throttleKey) / 60);
            $this->fail("Too many incorrect attempts. Please try again in {$minutes} minute(s).");
        }

        $db = Database::getInstance();
        $user = $db->query("SELECT password FROM users WHERE id = :id", [':id' => $userId])->fetch();
        if (!$user || !password_verify($current, $user['password'])) {
            RateLimiter::hit($throttleKey, self::MAX_FAILED_ATTEMPTS, self::LOCKOUT_SECONDS);
            $this->fail('Your current password is incorrect.');
        }

        if (mb_strlen($new) < self::MIN_PASSWORD_LENGTH) {
            $this->fail('The new password must be at least ' . self::MIN_PASSWORD_LENGTH . ' characters.');
        }
        if ($new !== $confirm) {
            $this->fail('The new passwords do not match.');
        }
        if ($new === $current || strtolower($new) === 'password' || password_verify($new, $user['password'])) {
            $this->fail('Choose a password different from your current one.');
        }

        $db->query("UPDATE users SET password = :password, must_change_password = 0, updated_at = NOW() WHERE id = :id", [
            ':password' => password_hash($new, PASSWORD_BCRYPT),
            ':id' => $userId,
        ]);
        RateLimiter::clear($throttleKey);

        // Fresh session ID after a credential change
        session_regenerate_id(true);
        $_SESSION['must_change_password'] = false;
        $_SESSION['success'] = 'Your password has been changed.';
        header('Location: /dashboard');
        exit;
    }

    private function fail(string $message): void
    {
        $_SESSION['error'] = $message;
        header('Location: /account/password');
        exit;
    }
}
