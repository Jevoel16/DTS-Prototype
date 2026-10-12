<?php

namespace App\Models;

use App\Core\Database;

class Document
{
    public $id;
    public $tracking_code;
    public $title;
    public $details;
    public $guest_info; // JSON string
    public $district;
    public $department;
    public $purpose_id;
    public $decline_reason;
    public $declined_at;
    public $status;
    public $version = 1;
    public $finalized_route; // JSON string
    public $current_step;
    public $current_department_id;
    public $released_at;
    public $released_by_user_id;
    public $created_at;
    public $updated_at;

    public static function findById(int $id): ?self
    {
        $db = Database::getInstance();
        $stmt = $db->query("SELECT * FROM documents WHERE id = :id", ['id' => $id]);
        $data = $stmt->fetch();
        return $data ? self::hydrate($data) : null;
    }

    public static function findByTrackingCode(string $trackingCode): ?self
    {
        $db = Database::getInstance();
        $stmt = $db->query("SELECT * FROM documents WHERE tracking_code = :tracking_code", ['tracking_code' => $trackingCode]);
        $data = $stmt->fetch();
        return $data ? self::hydrate($data) : null;
    }

    // Persists the current model state to the database, choosing either an INSERT or an UPDATE based on the presence of an ID.
    public function save(): bool
    {
        $db = Database::getInstance();
        $now = date('Y-m-d H:i:s');
        $this->updated_at = $now;

        if (isset($this->id)) {
            $sql = "UPDATE documents SET 
                tracking_code = :tracking_code, title = :title, details = :details, 
                guest_info = :guest_info, district = :district, department = :department, 
                purpose_id = :purpose_id, decline_reason = :decline_reason, declined_at = :declined_at, 
                status = :status, version = :version, finalized_route = :finalized_route, current_step = :current_step, 
                current_department_id = :current_department_id, released_at = :released_at, 
                released_by_user_id = :released_by_user_id, updated_at = :updated_at 
                WHERE id = :id";
                
            $db->query($sql, [
                'tracking_code' => $this->tracking_code,
                'title' => $this->title,
                'details' => $this->details,
                'guest_info' => $this->guest_info,
                'district' => $this->district,
                'department' => $this->department,
                'purpose_id' => $this->purpose_id,
                'decline_reason' => $this->decline_reason,
                'declined_at' => $this->declined_at,
                'status' => $this->status,
                'version' => $this->version,
                'finalized_route' => $this->finalized_route,
                'current_step' => $this->current_step,
                'current_department_id' => $this->current_department_id,
                'released_at' => $this->released_at,
                'released_by_user_id' => $this->released_by_user_id,
                'updated_at' => $this->updated_at,
                'id' => $this->id
            ]);
            return true;
        } else {
            $this->created_at = $now;
            $sql = "INSERT INTO documents 
                (tracking_code, title, details, guest_info, district, department, purpose_id, decline_reason, declined_at, status, finalized_route, current_step, current_department_id, released_at, released_by_user_id, created_at, updated_at) 
                VALUES 
                (:tracking_code, :title, :details, :guest_info, :district, :department, :purpose_id, :decline_reason, :declined_at, :status, :finalized_route, :current_step, :current_department_id, :released_at, :released_by_user_id, :created_at, :updated_at)";
                
            $db->query($sql, [
                'tracking_code' => $this->tracking_code,
                'title' => $this->title,
                'details' => $this->details,
                'guest_info' => $this->guest_info,
                'district' => $this->district,
                'department' => $this->department,
                'purpose_id' => $this->purpose_id,
                'decline_reason' => $this->decline_reason,
                'declined_at' => $this->declined_at,
                'status' => $this->status,
                'finalized_route' => $this->finalized_route,
                'current_step' => $this->current_step,
                'current_department_id' => $this->current_department_id,
                'released_at' => $this->released_at,
                'released_by_user_id' => $this->released_by_user_id,
                'created_at' => $this->created_at,
                'updated_at' => $this->updated_at
            ]);
            $this->id = $db->getConnection()->lastInsertId();
            return true;
        }
    }

    private static function hydrate(array $data): self
    {
        $model = new self();
        foreach ($data as $key => $value) {
            if (property_exists($model, $key)) {
                $model->$key = $value;
            }
        }
        return $model;
    }
}
