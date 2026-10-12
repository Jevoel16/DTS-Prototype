<?php

namespace App\Jobs;

/**
 * Runs the mysqldump / mysql command-line tools for backup and restore.
 * The password goes in MYSQL_PWD (never on the command line), and no shell is
 * involved, so the same code works on Windows (cmd.exe) and Linux.
 */
class MysqlCli
{
    /**
     * @param string      $tool   'mysqldump' or 'mysql' (must be on PATH)
     * @param string[]    $args   extra arguments after the connection options
     * @param string|null $stdin  file to feed on stdin (restore)
     * @param string|null $stdout file to write stdout to (backup)
     */
    public static function run(string $tool, array $args, ?string $stdin = null, ?string $stdout = null): void
    {
        $db = (require BASE_PATH . '/src/Config/config.php')['database'];

        $command = [
            $tool,
            '--host=' . $db['host'],
            '--port=' . $db['port'],
            '--user=' . $db['user'],
            ...$args,
            $db['dbname'],
        ];

        $null = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
        $descriptors = [
            0 => ['file', $stdin ?? $null, 'r'],
            1 => ['file', $stdout ?? $null, 'w'],
            2 => ['pipe', 'w'],
        ];

        $env = getenv();
        if ($db['password'] !== '') {
            $env['MYSQL_PWD'] = $db['password'];
        }

        $process = proc_open($command, $descriptors, $pipes, null, $env);
        if (!is_resource($process)) {
            throw new \Exception("Could not start $tool. Is the MySQL client on PATH?");
        }

        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            throw new \Exception("$tool failed (exit $exitCode): " . trim($stderr));
        }
    }
}
