<?php

$envPath = BASE_PATH . '/.env';
$env = [];

if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) {
            continue;
        }
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $env[trim($name)] = trim($value, " \t\n\r\0\x0B\"");
        }
    }
}

// Real environment variables (systemd, CI, one-off CLI runs) override .env
foreach (['DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD', 'APP_DEBUG', 'APP_ENV', 'APP_URL', 'APP_INTEGRITY_KEY', 'TRUSTED_PROXIES', 'BACKUP_PASSWORD', 'BACKUP_KEEP'] as $key) {
    $value = getenv($key);
    if ($value !== false) {
        $env[$key] = $value;
    }
}

return [
    'app' => [
        // Show PHP errors in the browser only when APP_DEBUG=true
        'debug' => filter_var($env['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOL),
        // 'production' forces Secure cookies and disables dev-only scripts
        'env' => $env['APP_ENV'] ?? 'local',
        // Public base URL (e.g. https://dts.example.gov.ph); used in printed tracking links
        'url' => $env['APP_URL'] ?? '',
        // Server-side secret that seals the document hash chain (HMAC). Never store it in the database.
        'integrity_key' => $env['APP_INTEGRITY_KEY'] ?? '',
        // Comma-separated proxy IPs allowed to supply the client IP (CF-Connecting-IP / X-Forwarded-For)
        'trusted_proxies' => array_filter(array_map('trim', explode(',', $env['TRUSTED_PROXIES'] ?? ''))),
        // Encrypts backup zips (AES-256). Store it outside the server too: without it backups cannot be restored
        'backup_password' => $env['BACKUP_PASSWORD'] ?? '',
        // Number of newest backup zips to keep; older ones are deleted after each new backup (0 = keep all)
        'backup_keep' => (int) ($env['BACKUP_KEEP'] ?? 0),
    ],
    'database' => [
        'host' => $env['DB_HOST'],
        'port' => $env['DB_PORT'],
        'dbname' => $env['DB_DATABASE'],
        'user' => $env['DB_USERNAME'],
        'password' => $env['DB_PASSWORD'],
        'charset' => 'utf8mb4'
    ]
];
