<?php

namespace App\Core;

/**
 * Append-only audit trail for system-level events that the database cannot record about itself,
 * such as a backup restore (which replaces the very tables a log row would live in).
 *
 * Each line is JSON: {ts, event, data, prev, mac}. `mac` is an HMAC (APP_INTEGRITY_KEY) over the
 * previous line's mac and this entry, so lines cannot be edited, reordered or removed from the
 * middle without verify() noticing. The file lives outside the database and outside backups.
 */
class SystemAudit
{
    public static function path(): string
    {
        return BASE_PATH . '/storage/logs/system-audit.log';
    }

    /**
     * Append one event. Never throws: an audit write failure is logged, not fatal to the caller.
     */
    public static function record(string $event, array $data = []): void
    {
        try {
            $dir = dirname(self::path());
            if (!is_dir($dir)) {
                mkdir($dir, 0770, true);
            }

            $handle = fopen(self::path(), 'c+');
            if ($handle === false) {
                throw new \RuntimeException('cannot open ' . self::path());
            }
            // Exclusive lock so concurrent writers (web + worker) chain correctly
            flock($handle, LOCK_EX);

            $previousMac = self::lastMac($handle);
            $entry = [
                'ts' => date('c'),
                'event' => $event,
                'data' => $data,
                'prev' => $previousMac,
            ];
            $entry['mac'] = self::macFor($entry);

            fseek($handle, 0, SEEK_END);
            fwrite($handle, json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
            fflush($handle);
            flock($handle, LOCK_UN);
            fclose($handle);
        } catch (\Throwable $e) {
            error_log("SystemAudit: could not record {$event}: " . $e->getMessage());
        }
    }

    /**
     * Check the whole file. Returns the 1-based line numbers that fail (empty when intact).
     *
     * @return int[]
     */
    public static function verify(): array
    {
        if (!file_exists(self::path())) {
            return [];
        }
        $bad = [];
        $previousMac = '';
        foreach (file(self::path(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $i => $line) {
            $entry = json_decode($line, true);
            $ok = is_array($entry) && isset($entry['mac'])
                && ($entry['prev'] ?? null) === $previousMac
                && hash_equals(self::macFor($entry), (string) $entry['mac']);
            if (!$ok) {
                $bad[] = $i + 1;
            }
            $previousMac = is_array($entry) ? (string) ($entry['mac'] ?? '') : '';
        }
        return $bad;
    }

    private static function macFor(array $entry): string
    {
        unset($entry['mac']);
        $payload = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $key = IntegrityManager::integrityKey();
        // Without a key the chain still detects accidental damage, though not deliberate edits
        return $key !== '' ? hash_hmac('sha256', $payload, $key) : hash('sha256', $payload);
    }

    /**
     * Mac of the last line in the (locked) file, or '' for an empty file.
     */
    private static function lastMac($handle): string
    {
        $size = fstat($handle)['size'] ?? 0;
        if ($size === 0) {
            return '';
        }
        // Lines are short; read the tail and take the last complete line
        $chunk = min($size, 65536);
        fseek($handle, -$chunk, SEEK_END);
        $lines = array_filter(explode("\n", (string) fread($handle, $chunk)), 'strlen');
        $last = json_decode((string) end($lines), true);
        return is_array($last) ? (string) ($last['mac'] ?? '') : '';
    }
}
