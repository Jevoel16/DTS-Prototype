<?php

namespace App\Controllers;

use App\Core\Database;

class ProfileController
{
    public function edit()
    {
        $db = Database::getInstance();
        $user = $db->query("SELECT * FROM users WHERE id = :id", ['id' => $_SESSION['user_id']])->fetch();
        
        require BASE_PATH . '/src/Views/profile/edit.php';
    }

    public function update()
    {
        // Simple profile update
        $db = Database::getInstance();
        $name = $_POST['name'] ?? '';
        $email = $_POST['email'] ?? '';

        $db->query("UPDATE users SET name = :name, email = :email, updated_at = NOW() WHERE id = :id", [
            'name' => $name,
            'email' => $email,
            'id' => $_SESSION['user_id']
        ]);

        header('Location: /profile');
        exit;
    }

    public function destroy()
    {
        $db = Database::getInstance();
        $db->query("DELETE FROM users WHERE id = :id", ['id' => $_SESSION['user_id']]);
        session_destroy();
        header('Location: /');
        exit;
    }
}
