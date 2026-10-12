<?php

/**
 * Router script for PHP's built-in server (fallback when NGINX is not installed):
 *   php -S 0.0.0.0:8000 -t public public/router.php
 *
 * Mirrors nginx's `try_files $uri $uri/ /index.php?$query_string`: real files are
 * served as-is, everything else (including URLs ending in a filename such as
 * /backups/download/backup.sql) goes to the front controller.
 */
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
if ($path !== '/' && is_file(__DIR__ . $path)) {
    return false;
}

$_SERVER['SCRIPT_NAME'] = '/index.php';
require __DIR__ . '/index.php';
