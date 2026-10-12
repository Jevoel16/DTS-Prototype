<?php

namespace App\Models;

use App\Core\Database;

class User
{
    public $id;
    public $name;
    public $email;
    public $email_verified_at;
    public $password;
    public $public_key;
    public $private_key;
    public $security_key_set_at;
    public $department_id;
    public $role;
    public $remember_token;
    public $created_at;
    public $updated_at;

    public static function findById(int $id): ?self
    {
        $db = Database::getInstance();
        $stmt = $db->query("SELECT * FROM users WHERE id = :id", ['id' => $id]);
        $data = $stmt->fetch();
        
        return $data ? self::hydrate($data) : null;
    }

    public static function findByEmail(string $email): ?self
    {
        $db = Database::getInstance();
        $stmt = $db->query("SELECT * FROM users WHERE email = :email", ['email' => $email]);
        $data = $stmt->fetch();
        
        return $data ? self::hydrate($data) : null;
    }

    public static function all(): array
    {
        $db = Database::getInstance();
        $stmt = $db->query("SELECT * FROM users");
        $results = $stmt->fetchAll();
        
        $users = [];
        foreach ($results as $row) {
            $users[] = self::hydrate($row);
        }
        return $users;
    }

    public function save(): bool
    {
        $db = Database::getInstance();
        $now = date('Y-m-d H:i:s');
        $this->updated_at = $now;

        if (isset($this->id)) {
            $sql = "UPDATE users SET 
                name = :name, email = :email, password = :password, 
                public_key = :public_key, private_key = :private_key, 
                security_key_set_at = :security_key_set_at, department_id = :department_id, 
                role = :role, updated_at = :updated_at 
                WHERE id = :id";
                
            $db->query($sql, [
                'name' => $this->name,
                'email' => $this->email,
                'password' => $this->password,
                'public_key' => $this->public_key,
                'private_key' => $this->private_key,
                'security_key_set_at' => $this->security_key_set_at,
                'department_id' => $this->department_id,
                'role' => $this->role,
                'updated_at' => $this->updated_at,
                'id' => $this->id
            ]);
            return true;
        } else {
            $this->created_at = $now;
            $sql = "INSERT INTO users 
                (name, email, password, public_key, private_key, security_key_set_at, department_id, role, created_at, updated_at) 
                VALUES 
                (:name, :email, :password, :public_key, :private_key, :security_key_set_at, :department_id, :role, :created_at, :updated_at)";
                
            $db->query($sql, [
                'name' => $this->name,
                'email' => $this->email,
                'password' => $this->password,
                'public_key' => $this->public_key,
                'private_key' => $this->private_key,
                'security_key_set_at' => $this->security_key_set_at,
                'department_id' => $this->department_id,
                'role' => $this->role,
                'created_at' => $this->created_at,
                'updated_at' => $this->updated_at
            ]);
            $this->id = $db->getConnection()->lastInsertId();
            return true;
        }
    }

    public function delete(): bool
    {
        if (isset($this->id)) {
            $db = Database::getInstance();
            $db->query("DELETE FROM users WHERE id = :id", ['id' => $this->id]);
            return true;
        }
        return false;
    }

    private static function hydrate(array $data): self
    {
        $user = new self();
        foreach ($data as $key => $value) {
            if (property_exists($user, $key)) {
                $user->$key = $value;
            }
        }
        return $user;
    }
}
