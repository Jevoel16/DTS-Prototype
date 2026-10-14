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

function columnExists(Database $db, string $schema, string $table, string $column): bool {
    return (bool) $db->query(
        'SELECT 1 FROM information_schema.columns WHERE table_schema = :s AND table_name = :t AND column_name = :c',
        ['s' => $schema, 't' => $table, 'c' => $column]
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
    ['documents',     'idx_doc_dept_status',     '(current_department_id, status)'],
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

// 4. Security hardening: rate limiter storage and the keyed seal on hash-chain logs
if (!tableExists($db, $schema, 'rate_limits')) {
    $db->query("CREATE TABLE rate_limits ( `key` VARCHAR(191) PRIMARY KEY, hits INT UNSIGNED NOT NULL DEFAULT 0, reset_at INT UNSIGNED NOT NULL, INDEX idx_rate_limits_reset (reset_at) )");
    echo "  + table rate_limits\n";
}
if (!columnExists($db, $schema, 'document_logs', 'seal')) {
    $db->query("ALTER TABLE document_logs ADD COLUMN seal CHAR(64) NULL AFTER hash");
    echo "  + document_logs.seal (set APP_INTEGRITY_KEY in .env, then run: composer run seal)\n";
}

// 5. Routing and staff authorization match departments by name, so names must be unique
if (!indexExists($db, $schema, 'departments', 'uniq_department_name')) {
    $dupes = $db->query("SELECT name FROM departments GROUP BY name HAVING COUNT(*) > 1")->fetchAll(\PDO::FETCH_COLUMN);
    if ($dupes) {
        echo "  ! departments has duplicate names (" . implode(', ', $dupes) . "); merge them, then re-run to add uniq_department_name\n";
    } else {
        $db->query("ALTER TABLE departments ADD UNIQUE INDEX uniq_department_name (name)");
        echo "  + departments.uniq_department_name\n";
    }
}

// 6. Chain seal: binds each document's workflow state and newest log entry (run `composer run seal` after)
if (!columnExists($db, $schema, 'documents', 'chain_seal')) {
    $db->query("ALTER TABLE documents ADD COLUMN chain_seal CHAR(64) NULL AFTER version");
    echo "  + documents.chain_seal (now run: composer run seal)\n";
}

// 7. Users are deactivated, never deleted: their logs and key history must stay verifiable
if (!columnExists($db, $schema, 'users', 'deactivated_at')) {
    $db->query("ALTER TABLE users ADD COLUMN deactivated_at TIMESTAMP NULL AFTER remember_token");
    echo "  + users.deactivated_at\n";
}
// 8. Forced password change: flag every account still using the seeded default password
if (!columnExists($db, $schema, 'users', 'must_change_password')) {
    $db->query("ALTER TABLE users ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0 AFTER deactivated_at");
    echo "  + users.must_change_password\n";
}
$flagged = 0;
foreach ($db->query("SELECT id, password FROM users WHERE must_change_password = 0")->fetchAll() as $row) {
    if (password_verify('password', (string) $row['password'])) {
        $db->query("UPDATE users SET must_change_password = 1 WHERE id = :id", ['id' => $row['id']]);
        $flagged++;
    }
}
if ($flagged) {
    echo "  ! $flagged account(s) still use the default password; they must change it at next sign-in\n";
}

$restrictFks = [
    ['document_logs', 'user_id'],
    ['user_public_key_histories', 'user_id'],
];
foreach ($restrictFks as [$table, $column]) {
    $fk = $db->query(
        "SELECT rc.constraint_name AS name, rc.delete_rule AS rule
         FROM information_schema.referential_constraints rc
         JOIN information_schema.key_column_usage k
           ON k.constraint_schema = rc.constraint_schema AND k.constraint_name = rc.constraint_name AND k.table_name = rc.table_name
         WHERE rc.constraint_schema = :s AND rc.table_name = :t AND k.column_name = :c AND rc.referenced_table_name = 'users'",
        ['s' => $schema, 't' => $table, 'c' => $column]
    )->fetch();
    if ($fk && $fk['rule'] === 'RESTRICT') {
        echo "  = $table.$column foreign key already RESTRICT\n";
        continue;
    }
    if ($fk) {
        $db->query("ALTER TABLE `$table` DROP FOREIGN KEY `{$fk['name']}`");
    }
    $db->query("ALTER TABLE `$table` ADD CONSTRAINT `fk_{$table}_{$column}` FOREIGN KEY (`$column`) REFERENCES users(id) ON DELETE RESTRICT");
    echo "  ~ $table.$column foreign key now ON DELETE RESTRICT\n";
}

echo "Done.\n";
