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
        
        if (!empty($_GET['role'])) {
            $where[] = "role = :role";
            $params[':role'] = $_GET['role'];
        }
        
        $whereSql = count($where) > 0 ? "WHERE " . implode(' AND ', $where) : "";
        
        $countSql = "SELECT COUNT(*) as total FROM users $whereSql";
        $countStmt = $db->query($countSql, $params);
        $totalItems = $countStmt->fetch()['total'] ?? 0;
        
        $paginator = new \App\Utils\Paginator($totalItems, 15, $page, '?page=(:num)&' . http_build_query(array_diff_key($_GET, ['page' => ''])));
        
        $sql = "SELECT id, name, email, role, department_id, created_at, public_key FROM users $whereSql ORDER BY name ASC LIMIT {$paginator->getLimit()} OFFSET {$paginator->getOffset()}";
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
            'role' => 'required',
            'password_confirmation' => 'required'
        ]);

        if (!empty($errors)) {
            $_SESSION['error'] = implode("<br>", $errors);
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

        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        
        $sql = "INSERT INTO users (name, email, password, role, department_id, created_at, updated_at) 
                VALUES (:name, :email, :password, :role, NULL, NOW(), NOW())";
                
        $db->query($sql, [
            ':name' => $name,
            ':email' => $email,
            ':password' => $hashedPassword,
            ':role' => $role
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
            'role' => 'required',
            'password' => '',
            'password_confirmation' => ''
        ]);

        if (!empty($errors)) {
            $_SESSION['error'] = implode("<br>", $errors);
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

        if (!empty($password)) {
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
            $sql = "UPDATE users SET name = :name, email = :email, role = :role, password = :password, updated_at = NOW() WHERE id = :id";
            $params = [
                ':name' => $name,
                ':email' => $email,
                ':role' => $role,
                ':password' => $hashedPassword,
                ':id' => $id
            ];
        } else {
            $sql = "UPDATE users SET name = :name, email = :email, role = :role, updated_at = NOW() WHERE id = :id";
            $params = [
                ':name' => $name,
                ':email' => $email,
                ':role' => $role,
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
        $db->query("DELETE FROM users WHERE id = :id", [':id' => $id]);
        
        $_SESSION['success'] = 'User deleted successfully.';
        header('Location: /users');
        exit;
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
