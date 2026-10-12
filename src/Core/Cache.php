<?php

namespace App\Core;

class Cache
{
    /**
     * Get a value from the cache.
     */
    public static function get($key)
    {
        $db = Database::getInstance();
        $stmt = $db->query("SELECT value, expiration FROM cache WHERE `key` = :key", [':key' => $key]);
        $result = $stmt->fetch();
        
        if ($result) {
            if (time() > (int)$result['expiration']) {
                self::forget($key);
                return null;
            }
            return json_decode($result['value'], true);
        }
        return null;
    }

    /**
     * Store a value in the cache.
     * 
     * @param string $key
     * @param mixed $value
     * @param int $ttlSeconds Time to live in seconds
     */
    public static function put($key, $value, $ttlSeconds)
    {
        $db = Database::getInstance();
        $expiration = time() + $ttlSeconds;
        $encodedValue = json_encode($value);
        
        $db->query("REPLACE INTO cache (`key`, `value`, `expiration`) VALUES (:key, :val, :exp)", [
            ':key' => $key,
            ':val' => $encodedValue,
            ':exp' => $expiration
        ]);
    }

    /**
     * Remove a value from the cache.
     */
    public static function forget($key)
    {
        $db = Database::getInstance();
        $db->query("DELETE FROM cache WHERE `key` = :key", [':key' => $key]);
    }

    /**
     * Retrieve a value, or cache it if it doesn't exist.
     */
    public static function remember($key, $ttlSeconds, callable $callback)
    {
        $value = self::get($key);
        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        self::put($key, $value, $ttlSeconds);
        return $value;
    }

    /**
     * Remove all entries from the cache.
     */
    public static function clear()
    {
        $db = Database::getInstance();
        $db->query("DELETE FROM cache");
    }
}
