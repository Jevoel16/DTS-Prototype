<?php

namespace App\Controllers;

use App\Core\Database;
use App\Core\Validator;

class AuthController
{
    /**
     * Show the login form.
     */
    public function showLogin()
    {
        // If already logged in, redirect to dashboard based on role
        if (isset($_SESSION['user_id'])) {
            $this->redirectBasedOnRole($_SESSION['role']);
        }

        require BASE_PATH . '/src/Views/auth/login.php';
    }

    /**
     * Handle the login form submission.
     */
    public function login()
    {
        [$errors, $validated] = Validator::validate($_POST, [
            'email' => 'required',
            'password' => 'required'
        ]);

        if (!empty($errors)) {
            $_SESSION['error'] = implode("<br>", $errors);
            $_SESSION['field_errors'] = $errors;
            $old = $_POST;
            unset($old['password']);
            $_SESSION['old'] = $old;
            header("Location: /login");
            exit;
        }

        $email = $validated['email'];
        $password = $validated['password'];

        $db = Database::getInstance();
        $stmt = $db->query("SELECT * FROM users WHERE email = :email LIMIT 1", [':email' => $email]);
        $user = $stmt->fetch();

        // Verify the user exists and the password is correct
        if ($user && password_verify($password, $user['password'])) {
            // Success! Store user details in session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['department_id'] = $user['department_id'];
            $_SESSION['private_key'] = $user['private_key']; // Required for client-side signing

            if ($user['department_id']) {
                $deptStmt = $db->query("SELECT name FROM departments WHERE id = :id", [':id' => $user['department_id']]);
                $dept = $deptStmt->fetch();
                if ($dept) {
                    $_SESSION['department_name'] = $dept['name'];
                }
            }

            // Prevent session fixation
            session_regenerate_id(true);

            $this->redirectBasedOnRole($user['role']);
        } else {
            // Failed login
            $_SESSION['error'] = "These credentials do not match our records.";
            $_SESSION['field_errors'] = [
                'email' => 'Invalid username or password.',
                'password' => 'Invalid username or password.'
            ];
            $old = $_POST;
            unset($old['password']);
            $_SESSION['old'] = $old;
            header("Location: /login");
            exit;
        }
    }

    /**
     * Log the user out and destroy the session.
     */
    public function logout()
    {
        // Unset all session variables
        $_SESSION = array();

        // Destroy the session cookie if it exists
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }

        // Destroy the session
        session_destroy();

        header("Location: /login");
        exit;
    }

    /**
     * Helper to route users to their specific dashboard based on role.
     */
    private function redirectBasedOnRole($role)
    {
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
