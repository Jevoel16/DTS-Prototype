<?php
/**
 * Brings an existing database up to date with database.sql without losing data.
 * Safe to run any number of times: every step checks information_schema first.
 *
 *   composer run migrate
 */
define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/vendor/autoload.php';

use App\Core\Database;

$db = Database::getInstance();
$schema = $db->query('SELECT DATABASE() AS db')->fetch()['db'];

function tableExists(Database $db, string $schema, string $table): bool {
    return (bool) $db->query(
        'SELECT 1 FROM information_schema.tables WHERE table_schema = :s AND table_name = :t',
        ['s' => $schema, 't' => $table]
    )->fetch();
}

function indexExists(Database $db, string $schema, string $table, string $index): bool {
    return (bool) $db->query(
        'SELECT 1 FROM information_schema.statistics WHERE table_schema = :s AND table_name = :t AND index_name = :i',
        ['s' => $schema, 't' => $table, 'i' => $index]
    )->fetch();
}

echo "Migrating database `$schema`...\n";

// 1. Indexes used by the worker poll, notifications, name lookups, reports and cache GC
$indexes = [
    ['cache',         'idx_cache_expiration',    '(expiration)'],
    ['jobs',          'idx_jobs_pending',        '(reserved_at, available_at)'],
    ['departments',   'idx_department_name',     '(name)'],
    ['purposes',      'idx_purpose_name',        '(name)'],
    ['documents',     'idx_doc_updated_at',      '(updated_at)'],
    ['notifications', 'idx_notif_dept_unread',   '(department_id, is_read, created_at)'],
    ['notifications', 'idx_notif_user_unread',   '(user_id, is_read, created_at)'],
];
foreach ($indexes as [$table, $index, $columns]) {
    if (indexExists($db, $schema, $table, $index)) {
        echo "  = $table.$index already exists\n";
        continue;
    }
    $db->query("ALTER TABLE `$table` ADD INDEX `$index` $columns");
    echo "  + $table.$index\n";
}

// 2. Single-column notification indexes are now covered by the composites above.
//    (idx_department / idx_user also back the foreign keys, so only drop them once
//    the composites exist; MySQL then uses the composites for the FKs.)
foreach (['idx_department', 'idx_user', 'idx_unread'] as $index) {
    if (indexExists($db, $schema, 'notifications', $index)) {
        $db->query("ALTER TABLE notifications DROP INDEX `$index`");
        echo "  - notifications.$index\n";
    }
}

// 3. Laravel-era tables the PHP app never uses
foreach (['job_batches', 'password_reset_tokens', 'sessions', 'cache_locks', 'prediction_keywords'] as $table) {
    if (tableExists($db, $schema, $table)) {
        $db->query("DROP TABLE `$table`");
        echo "  - table $table\n";
    }
}

echo "Done.\n";
