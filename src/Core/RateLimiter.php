<?php

namespace App\Core;

/**
 * Fixed-window rate limiter stored in the `rate_limits` table, so limits hold across
 * PHP-FPM workers and multiple app servers. Uses its own DB connection so a counted
 * failure survives a rollback of the caller's transaction.
 */
class RateLimiter
{
    /**
     * Count one hit against $key and report whether it is still within $max hits per $windowSeconds.
     */
    public static function hit(string $key, int $max, int $windowSeconds): bool
    {
        $db = Database::separateInstance();
        $now = time();

        // Atomic upsert: start a new window when the old one has expired, otherwise increment
        $db->query(
            "INSERT INTO rate_limits (`key`, hits, reset_at) VALUES (:key, 1, :reset)
             ON DUPLICATE KEY UPDATE
                hits = IF(reset_at <= :now1, 1, hits + 1),
                reset_at = IF(reset_at <= :now2, :reset2, reset_at)",
            [':key' => self::normalize($key), ':reset' => $now + $windowSeconds, ':now1' => $now, ':now2' => $now, ':reset2' => $now + $windowSeconds]
        );

        return self::hits($key) <= $max;
    }

    /**
     * True when $key has already used up $max hits in its current window (does not count a hit).
     */
    public static function tooMany(string $key, int $max): bool
    {
        return self::hits($key) >= $max;
    }

    /**
     * Seconds until the current window for $key resets (0 when there is none).
     */
    public static function availableIn(string $key): int
    {
        $row = Database::separateInstance()->query(
            "SELECT reset_at FROM rate_limits WHERE `key` = :key",
            [':key' => self::normalize($key)]
        )->fetch();
        return $row ? max(0, (int) $row['reset_at'] - time()) : 0;
    }

    public static function clear(string $key): void
    {
        Database::separateInstance()->query("DELETE FROM rate_limits WHERE `key` = :key", [':key' => self::normalize($key)]);
    }

    /**
     * Client IP. Forwarded headers are trusted only when the direct peer is a configured proxy
     * (TRUSTED_PROXIES), e.g. cloudflared on the same host; otherwise anyone could spoof them.
     */
    public static function clientIp(): string
    {
        $remote = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $trusted = (require BASE_PATH . '/src/Config/config.php')['app']['trusted_proxies'];

        if (in_array($remote, $trusted, true)) {
            $forwarded = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '';
            if ($forwarded === '' && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $forwarded = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
            }
            if (filter_var($forwarded, FILTER_VALIDATE_IP)) {
                return $forwarded;
            }
        }

        return $remote;
    }

    private static function hits(string $key): int
    {
        $row = Database::separateInstance()->query(
            "SELECT hits, reset_at FROM rate_limits WHERE `key` = :key",
            [':key' => self::normalize($key)]
        )->fetch();

        if (!$row || (int) $row['reset_at'] <= time()) {
            return 0;
        }
        return (int) $row['hits'];
    }

    // Keys can contain user input (emails); hash long ones to fit the primary key
    private static function normalize(string $key): string
    {
        return strlen($key) > 120 ? substr($key, 0, 60) . ':' . hash('sha256', $key) : $key;
    }
}
