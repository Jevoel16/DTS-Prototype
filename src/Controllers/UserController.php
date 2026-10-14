<?php

namespace App\Controllers;

use App\Core\Database;
use App\Core\Validator;

class UserController
{
    public function index()
    {
        $db = Database::getInstance();
        $page = $_GET['page'] ?? 1;
        
        $where = [];
        $params = [];
        
        if (!empty($_GET['search'])) {
            $where[] = "(name LIKE :search OR email LIKE :search)";
            $params[':search'] = '%' . $_GET['search'] . '%';
        }
        
        if (!empty($_GET['role']) && $_GET['role'] !== 'all') {
            $where[] = "role = :role";
            $params[':role'] = $_GET['role'];
        }

        // Deactivated accounts are hidden unless asked for (?status=deactivated or ?status=all)
        $status = $_GET['status'] ?? 'active';
        if ($status === 'deactivated') {
            $where[] = "deactivated_at IS NOT NULL";
        } elseif ($status !== 'all') {
            $where[] = "deactivated_at IS NULL";
        }
        
        $whereSql = count($where) > 0 ? "WHERE " . implode(' AND ', $where) : "";
        
        $countSql = "SELECT COUNT(*) as total FROM users $whereSql";
        $countStmt = $db->query($countSql, $params);
        $totalItems = $countStmt->fetch()['total'] ?? 0;
        
        $paginator = new \App\Utils\Paginator($totalItems, 15, $page, '?page=(:num)&' . http_build_query(array_diff_key($_GET, ['page' => ''])));
        
        $sql = "SELECT id, name, email, role, department_id, created_at, public_key, deactivated_at FROM users $whereSql ORDER BY name ASC LIMIT {$paginator->getLimit()} OFFSET {$paginator->getOffset()}";
        $stmt = $db->query($sql, $params);
        $users = $stmt->fetchAll();
        
        require BASE_PATH . '/src/Views/admin/users/index.php';
    }

    public function create()
    {
        $db = Database::getInstance();
        $departments = $db->query("SELECT * FROM departments ORDER BY name ASC")->fetchAll();
        require BASE_PATH . '/src/Views/admin/users/create.php';
    }

    public function store()
    {
        [$errors, $validated] = Validator::validate($_POST, [
            'name' => 'required',
            'email' => 'required',
            'password' => 'required|min:8',
            'role' => 'required|in:admin,officer,staff',
            'password_confirmation' => 'required'
        ]);

        if (!empty($errors)) {
            $_SESSION['error'] = implode(' ', $errors);
            header("Location: /users/create");
            exit;
        }

        $name = $validated['name'];
        $email = $validated['email'];
        $password = $validated['password'];
        $password_confirmation = $validated['password_confirmation'];
        $role = $validated['role'];

        if ($password !== $password_confirmation) {
            $_SESSION['error'] = 'Passwords do not match.';
            header('Location: /users/create');
            exit;
        }

        $db = Database::getInstance();
        
        // Check if email exists
        $stmt = $db->query("SELECT id FROM users WHERE email = :email", [':email' => $email]);
        if ($stmt->fetch()) {
            $_SESSION['error'] = 'Email already in use.';
            header('Location: /users/create');
            exit;
        }

        [$departmentError, $departmentId] = $this->resolveDepartment($role, $_POST['department_id'] ?? '');
        if ($departmentError) {
            $_SESSION['error'] = $departmentError;
            header('Location: /users/create');
            exit;
        }

        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

        // The admin-chosen password is temporary: the user replaces it at first sign-in
        $sql = "INSERT INTO users (name, email, password, role, department_id, must_change_password, created_at, updated_at)
                VALUES (:name, :email, :password, :role, :department_id, 1, NOW(), NOW())";

        $db->query($sql, [
            ':name' => $name,
            ':email' => $email,
            ':password' => $hashedPassword,
            ':role' => $role,
            ':department_id' => $departmentId
        ]);

        $_SESSION['success'] = 'User created successfully.';
        header('Location: /users');
        exit;
    }

    public function edit($id)
    {
        $db = Database::getInstance();
        $user = $db->query("SELECT * FROM users WHERE id = :id", [':id' => $id])->fetch();
        
        if (!$user) {
            $_SESSION['error'] = 'User not found.';
            header('Location: /users');
            exit;
        }

        $departments = $db->query("SELECT * FROM departments ORDER BY name ASC")->fetchAll();
        require BASE_PATH . '/src/Views/admin/users/edit.php';
    }

    public function update($id)
    {
        [$errors, $validated] = Validator::validate($_POST, [
            'name' => 'required',
            'email' => 'required',
            'role' => 'required|in:admin,officer,staff',
            'password' => '',
            'password_confirmation' => ''
        ]);

        if (!empty($errors)) {
            $_SESSION['error'] = implode(' ', $errors);
            header("Location: /users/{$id}/edit");
            exit;
        }

        $name = $validated['name'];
        $email = $validated['email'];
        $role = $validated['role'];
        $password = $validated['password'];
        $password_confirmation = $validated['password_confirmation'];

        if (!empty($password) && $password !== $password_confirmation) {
            $_SESSION['error'] = 'Passwords do not match.';
            header("Location: /users/{$id}/edit");
            exit;
        }

        // Same minimum as account creation
        if (!empty($password) && strlen($password) < 8) {
            $_SESSION['error'] = 'Password must be at least 8 characters.';
            header("Location: /users/{$id}/edit");
            exit;
        }

        $db = Database::getInstance();

        // Check if email exists for other users
        $stmt = $db->query("SELECT id FROM users WHERE email = :email AND id != :id", [
            ':email' => $email,
            ':id' => $id
        ]);
        
        if ($stmt->fetch()) {
            $_SESSION['error'] = 'Email already in use by another account.';
            header("Location: /users/{$id}/edit");
            exit;
        }

        [$departmentError, $departmentId] = $this->resolveDepartment($role, $_POST['department_id'] ?? '');
        if ($departmentError) {
            $_SESSION['error'] = $departmentError;
            header("Location: /users/{$id}/edit");
            exit;
        }

        if (!empty($password)) {
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
            // A password set by an admin for someone else is temporary and must be replaced at next sign-in
            $sql = "UPDATE users SET name = :name, email = :email, role = :role, department_id = :department_id, password = :password, must_change_password = :must_change, updated_at = NOW() WHERE id = :id";
            $params = [
                ':name' => $name,
                ':email' => $email,
                ':role' => $role,
                ':department_id' => $departmentId,
                ':password' => $hashedPassword,
                ':must_change' => ((int) $id !== (int) $_SESSION['user_id']) ? 1 : 0,
                ':id' => $id
            ];
        } else {
            $sql = "UPDATE users SET name = :name, email = :email, role = :role, department_id = :department_id, updated_at = NOW() WHERE id = :id";
            $params = [
                ':name' => $name,
                ':email' => $email,
                ':role' => $role,
                ':department_id' => $departmentId,
                ':id' => $id
            ];
        }

        $db->query($sql, $params);

        $_SESSION['success'] = 'User updated successfully.';
        header('Location: /users');
        exit;
    }

    public function destroy($id)
    {
        // Check if it's not the currently logged in admin trying to delete themselves
        if ($_SESSION['user_id'] == $id) {
            $_SESSION['error'] = 'You cannot delete your own account.';
            header('Location: /users');
            exit;
        }

        $db = Database::getInstance();
        // Accounts are deactivated, never deleted: their signed log entries and key history must
        // stay attributable and verifiable. AuthMiddleware ends a deactivated user's sessions.
        $db->query("UPDATE users SET deactivated_at = NOW(), updated_at = NOW() WHERE id = :id AND deactivated_at IS NULL", [':id' => $id]);

        $_SESSION['success'] = 'User deactivated. Their history is kept and they can no longer sign in.';
        header('Location: /users');
        exit;
    }

    /**
     * Staff need a department and Records Officers the Records Unit, since routing and
     * permissions match on it; admins have none.
     *
     * @return array{0: ?string, 1: ?int} [error message, department ID]
     */
    private function resolveDepartment(string $role, $departmentInput): array
    {
        if ($role === 'admin') {
            return [null, null];
        }
        $department = ctype_digit((string) $departmentInput)
            ? Database::getInstance()->query("SELECT id, name FROM departments WHERE id = :id", [':id' => (int) $departmentInput])->fetch()
            : null;
        if (!$department) {
            return ['Please choose a department for this account.', null];
        }
        if ($role === 'officer' && $department['name'] !== 'Records Unit') {
            return ['Records Officers must belong to the Records Unit.', null];
        }
        return [null, (int) $department['id']];
    }

    public function resetSignature($id)
    {
        if (($_SESSION['role'] ?? '') !== 'admin') {
            $_SESSION['error'] = 'Unauthorized action.';
            header('Location: /users');
            exit;
        }

        $db = Database::getInstance();
        
        $db->query("UPDATE user_public_key_histories SET deactivated_at = NOW(), updated_at = NOW() WHERE user_id = :id AND deactivated_at IS NULL", [':id' => $id]);
        $db->query("UPDATE users SET public_key = NULL, private_key = NULL, security_key_set_at = NULL WHERE id = :id", [':id' => $id]);
        
        // Clear the user's personal page cache so they see the setup modal immediately on their next request
        $prefix = "cache_user_{$id}";
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

        $_SESSION['success'] = 'User digital signature reset successfully.';
        header('Location: /users');
        exit;
    }
}
