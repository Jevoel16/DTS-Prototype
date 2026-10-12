<?php

/**
 * Shared server setup helpers used by dev.php and prod.php.
 *
 * Linux:   NGINX + PHP-FPM (system services, config in /etc/nginx, cert in /etc/ssl)
 * Windows: NGINX for Windows + a small pool of php-cgi.exe FastCGI processes,
 *          all project-local (config in storage/nginx, cert in storage/ssl)
 */

const DTS_PORT              = 8000;
const DTS_WIN_FASTCGI_PORT  = 9123; // first php-cgi port; the pool uses consecutive ports
const DTS_WIN_FASTCGI_POOL  = 4;    // php-cgi.exe handles one request at a time, so run several

function projectRoot(): string {
    return realpath(__DIR__ . '/..');
}

function isWindows(): bool {
    return PHP_OS_FAMILY === 'Windows';
}

function executeCommand(string $command): void {
    passthru($command, $status);
    if ($status !== 0) {
        throw new RuntimeException("Command failed with exit code $status: $command");
    }
}

/**
 * Read a single key from the project .env (used for optional NGINX_PATH).
 */
function envValue(string $key): ?string {
    $envPath = projectRoot() . '/.env';
    if (!is_file($envPath)) {
        return null;
    }
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) {
            continue;
        }
        [$name, $value] = explode('=', $line, 2);
        if (trim($name) === $key) {
            $value = trim($value, " \t\n\r\0\x0B\"");
            return $value === '' ? null : $value;
        }
    }
    return null;
}

/**
 * Detect the machine's primary LAN IPv4 address.
 * A connected UDP socket sends nothing but reveals which local address the OS
 * would route through, which works the same on Windows and Linux.
 * Falls back to 127.0.0.1 if none is found (e.g. no network connection).
 */
function detectLanIp(): string {
    $socket = @stream_socket_client('udp://8.8.8.8:53', $errno, $errstr, 1);
    if ($socket) {
        $name = stream_socket_get_name($socket, false);
        fclose($socket);
        $ip = $name ? substr($name, 0, strrpos($name, ':')) : '';
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && $ip !== '127.0.0.1') {
            return $ip;
        }
    }

    if (!isWindows()) {
        $output = shell_exec("ip -4 addr show scope global | grep -oP '(?<=inet )[\d.]+'");
        if ($output) {
            $ips = array_filter(array_map('trim', explode("\n", $output)));
            if (!empty($ips)) {
                return reset($ips);
            }
        }
    }
    return '127.0.0.1';
}

/**
 * Where the self-signed cert lives on this OS.
 * @return array{cert: string, key: string}
 */
function sslPaths(): array {
    if (isWindows()) {
        $dir = projectRoot() . '\\storage\\ssl';
        return ['cert' => "$dir\\dts_prototype.crt", 'key' => "$dir\\dts_prototype.key"];
    }
    return ['cert' => '/etc/ssl/certs/dts_prototype.crt', 'key' => '/etc/ssl/private/dts_prototype.key'];
}

/**
 * Shell-ready openssl command. On Windows: OPENSSL_PATH in .env (exe or its folder),
 * then PATH, then the copy bundled with Git for Windows.
 */
function opensslCommand(): string {
    if (!isWindows()) {
        return 'openssl';
    }

    $candidates = [];
    if ($configured = envValue('OPENSSL_PATH')) {
        $candidates[] = is_dir($configured) ? rtrim($configured, '\\/') . '\\openssl.exe' : $configured;
    }
    foreach ($candidates as $candidate) {
        if (is_file($candidate)) {
            return escapeshellarg(realpath($candidate));
        }
    }

    $where = trim((string) shell_exec('where openssl 2>NUL'));
    if ($where !== '') {
        return escapeshellarg(strtok($where, "\r\n"));
    }

    foreach ([getenv('ProgramFiles'), getenv('ProgramFiles(x86)'), getenv('LOCALAPPDATA') . '\\Programs'] as $base) {
        foreach (['\\Git\\usr\\bin\\openssl.exe', '\\Git\\mingw64\\bin\\openssl.exe'] as $suffix) {
            if ($base && is_file($base . $suffix)) {
                return escapeshellarg($base . $suffix);
            }
        }
    }

    throw new RuntimeException('openssl not found. Install Git for Windows, add openssl to PATH, or set OPENSSL_PATH in .env.');
}

/**
 * Generate (or regenerate) the self-signed SSL cert with SANs covering:
 *   - localhost
 *   - 127.0.0.1
 *   - the current LAN IP
 *
 * This ensures browsers on other LAN machines show the "proceed anyway" bypass.
 * The cert is regenerated if the LAN IP has changed since last run.
 */
function ensureSslCert(string $lanIp): void {
    ['cert' => $certPath, 'key' => $keyPath] = sslPaths();
    $sudo = isWindows() ? '' : 'sudo ';
    $openssl = opensslCommand();

    // Check if the existing cert already covers this LAN IP
    if (file_exists($certPath)) {
        $certInfo = shell_exec("$openssl x509 -in " . escapeshellarg($certPath) . ' -noout -text');
        if ($certInfo && str_contains($certInfo, "IP Address:$lanIp")) {
            echo "✅ SSL cert already valid for $lanIp — skipping regeneration.\n";
            return;
        }
    }

    if (isWindows() && !is_dir(dirname($certPath))) {
        mkdir(dirname($certPath), 0770, true);
    }

    echo "🔐 Generating SSL cert with SANs for localhost + $lanIp...\n";

    // Build a temporary OpenSSL config with SANs
    $opensslCnf = tempnam(sys_get_temp_dir(), 'dts_openssl_');
    file_put_contents($opensslCnf, <<<CNF
    [req]
    default_bits       = 2048
    prompt             = no
    default_md         = sha256
    distinguished_name = dn
    x509_extensions    = v3_req

    [dn]
    CN = DTS Prototype

    [v3_req]
    subjectAltName = @alt_names
    basicConstraints = CA:FALSE
    keyUsage = digitalSignature, keyEncipherment
    extendedKeyUsage = serverAuth

    [alt_names]
    DNS.1 = localhost
    IP.1  = 127.0.0.1
    IP.2  = $lanIp
    CNF);

    $cmd = "{$sudo}{$openssl} req -x509 -nodes -days 825 -newkey rsa:2048"
         . ' -keyout ' . escapeshellarg($keyPath)
         . ' -out ' . escapeshellarg($certPath)
         . ' -config ' . escapeshellarg($opensslCnf)
         . ' 2>&1';

    executeCommand($cmd);
    unlink($opensslCnf);

    if (!isWindows()) {
        // Ensure the ssl-cert group can read the key
        executeCommand("sudo chgrp ssl-cert $keyPath");
        executeCommand("sudo chmod 640 $keyPath");
    }

    echo "✅ SSL cert generated (valid 825 days, covers localhost + $lanIp).\n";
}

/**
 * Render nginx/site.conf with the values for this machine.
 */
function renderSiteConfig(string $projectRoot, string $fastcgiPass, string $lanIp, string $logDir): string {
    $ssl = sslPaths();
    $slashes = fn(string $p) => str_replace('\\', '/', $p);

    // Drop the template's header comment (it names the placeholders, which strtr would rewrite)
    $template = preg_replace('/\A(?:#.*\R)+/', "# Generated from nginx/site.conf by scripts/server-lib.php — edit the template, not this file.\n", file_get_contents($projectRoot . '/nginx/site.conf'));

    return strtr($template, [
        '__PROJECT_ROOT__' => $slashes($projectRoot),
        '__FASTCGI_PASS__' => $fastcgiPass,
        '__LAN_IP__'       => $lanIp,
        '__SSL_CERT__'     => $slashes($ssl['cert']),
        '__SSL_KEY__'      => $slashes($ssl['key']),
        '__LOG_DIR__'      => $slashes($logDir),
    ]);
}

/**
 * Linux: write the resolved NGINX site config into sites-available
 * and ensure the sites-enabled symlink exists.
 */
function installNginxConfig(string $projectRoot, string $phpVersion, string $lanIp): void {
    $nginxTarget = '/etc/nginx/sites-available/dts-prototype';
    $nginxLink   = '/etc/nginx/sites-enabled/dts-prototype';

    $nginxConf = renderSiteConfig($projectRoot, "unix:/run/php/php{$phpVersion}-fpm.sock", $lanIp, '/var/log/nginx');

    // Write via sudo tee — /etc/nginx/sites-available/ is root-owned
    $escapedConf = escapeshellarg($nginxConf);
    executeCommand("echo $escapedConf | sudo tee $nginxTarget > /dev/null");

    if (!file_exists($nginxLink)) {
        executeCommand("sudo ln -s $nginxTarget $nginxLink");
    }

    echo "✅ NGINX site config installed from project (LAN IP: $lanIp).\n";
}

/**
 * Start the async queue worker (console.php) in the background.
 * Linux uses nohup so it survives the terminal; Windows keeps the handle so
 * the caller can stop it on exit.
 *
 * @return resource|null
 */
function startQueueWorker() {
    $consolePath = projectRoot() . DIRECTORY_SEPARATOR . 'console.php';
    $logDir = projectRoot() . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0770, true);
    }
    $logFile = $logDir . DIRECTORY_SEPARATOR . 'worker.log';

    echo "⚙️  Starting Async Queue Worker (console.php) → storage/logs/worker.log\n";

    if (isWindows()) {
        return startBackground([PHP_BINARY, $consolePath], $logFile);
    }

    // Find and kill any existing console.php to avoid duplicates
    passthru("pkill -f 'php $consolePath' > /dev/null 2>&1");
    exec("nohup php " . escapeshellarg($consolePath) . " > " . escapeshellarg($logFile) . " 2>&1 &");
    return null;
}

// ---------------------------------------------------------------------------
// Windows helpers
// ---------------------------------------------------------------------------

/**
 * Locate nginx.exe: NGINX_PATH in .env (exe or its folder), then C:\nginx, then PATH.
 * Folders are also searched up to two levels deep, since extracting the nginx zip
 * usually nests it (e.g. C:\nginx\nginx-1.30.5\nginx-1.30.5\nginx.exe).
 */
function findNginxWindows(): ?string {
    $dirs = [];
    $candidates = [];
    if ($configured = envValue('NGINX_PATH')) {
        if (is_dir($configured)) {
            $dirs[] = rtrim($configured, '\\/');
        } else {
            $candidates[] = $configured;
        }
    }
    $dirs[] = 'C:\\nginx';

    foreach ($dirs as $dir) {
        array_push($candidates, "$dir\\nginx.exe", ...glob("$dir/*/nginx.exe"), ...glob("$dir/*/*/nginx.exe"));
    }

    foreach ($candidates as $candidate) {
        if (is_file($candidate)) {
            return realpath($candidate);
        }
    }

    $where = trim((string) shell_exec('where nginx 2>NUL'));
    if ($where !== '') {
        return strtok($where, "\r\n");
    }
    return null;
}

/**
 * Windows: build a project-local nginx prefix (storage/nginx) with a complete
 * nginx.conf that wraps the shared site template. Returns the prefix directory.
 */
function installNginxConfigWindows(string $projectRoot, string $nginxExe, string $lanIp): string {
    $prefix = $projectRoot . '\\storage\\nginx';
    foreach (['', '\\logs', '\\temp', '\\conf'] as $dir) {
        if (!is_dir($prefix . $dir)) {
            mkdir($prefix . $dir, 0770, true);
        }
    }

    // nginx for Windows ships mime.types and fastcgi_params in its own conf folder
    $nginxConfDir = dirname($nginxExe) . '\\conf';
    foreach (['mime.types', 'fastcgi_params'] as $file) {
        if (!is_file("$nginxConfDir\\$file")) {
            throw new RuntimeException("Missing $nginxConfDir\\$file — is this a complete nginx for Windows install?");
        }
        copy("$nginxConfDir\\$file", "$prefix\\conf\\$file");
    }

    $servers = '';
    for ($i = 0; $i < DTS_WIN_FASTCGI_POOL; $i++) {
        $servers .= '        server 127.0.0.1:' . (DTS_WIN_FASTCGI_PORT + $i) . ";\n";
    }

    $site = renderSiteConfig($projectRoot, 'dts_php', $lanIp, $projectRoot . '/storage/logs');

    $conf = <<<CONF
    # Generated by scripts/server-lib.php from nginx/site.conf — do not edit.
    worker_processes  1;
    error_log  logs/error.log;
    pid        logs/nginx.pid;

    events {
        worker_connections  1024;
    }

    http {
        include       mime.types;
        default_type  application/octet-stream;
        sendfile      on;
        keepalive_timeout  65;

        upstream dts_php {
    $servers    }

    $site
    }

    CONF;

    file_put_contents("$prefix\\conf\\nginx.conf", $conf);
    echo "✅ NGINX config written to storage\\nginx\\conf\\nginx.conf (LAN IP: $lanIp).\n";

    return $prefix;
}

/**
 * Windows: start a background process without a shell so we keep its real PID.
 * Output goes to $logFile (or is discarded).
 *
 * @return resource proc_open handle
 */
function startBackground(array $command, ?string $logFile = null, ?array $env = null) {
    $out = $logFile ? ['file', $logFile, 'a'] : ['file', 'NUL', 'w'];
    $process = proc_open($command, [0 => ['file', 'NUL', 'r'], 1 => $out, 2 => $out], $pipes, projectRoot(), $env, ['bypass_shell' => true]);
    if (!is_resource($process)) {
        throw new RuntimeException('Failed to start: ' . implode(' ', $command));
    }
    return $process;
}

/**
 * Windows: stop processes started with startBackground().
 */
function stopBackground(array $processes): void {
    foreach ($processes as $process) {
        if (!is_resource($process)) {
            continue;
        }
        $pid = proc_get_status($process)['pid'] ?? null;
        if ($pid) {
            // /T also stops children (console.php spawns runner.php jobs)
            shell_exec("taskkill /F /T /PID $pid >NUL 2>&1");
        }
        proc_close($process);
    }
}

/**
 * Windows: start the php-cgi.exe FastCGI pool used by nginx.
 * @return resource[]
 */
function startPhpCgiPool(): array {
    $phpCgi = dirname(PHP_BINARY) . '\\php-cgi.exe';
    if (!is_file($phpCgi)) {
        throw new RuntimeException("php-cgi.exe not found next to " . PHP_BINARY);
    }

    // Each php-cgi process normally exits after 500 requests; 0 = never.
    $env = getenv();
    $env['PHP_FCGI_MAX_REQUESTS'] = '0';

    $processes = [];
    for ($i = 0; $i < DTS_WIN_FASTCGI_POOL; $i++) {
        $processes[] = startBackground([$phpCgi, '-b', '127.0.0.1:' . (DTS_WIN_FASTCGI_PORT + $i)], null, $env);
    }
    return $processes;
}

/**
 * Windows: run NGINX + php-cgi pool (+ optional worker) in the foreground until
 * Ctrl+C, then stop everything. Returns false if nginx is not installed so the
 * caller can fall back to the PHP built-in server.
 */
function runWindowsNginx(bool $withWorker, string $mode): bool {
    $nginxExe = findNginxWindows();
    if ($nginxExe === null) {
        return false;
    }

    $root  = projectRoot();
    $lanIp = detectLanIp();
    echo "🌐 Detected LAN IP: $lanIp\n";
    echo "🧭 Using NGINX at $nginxExe\n";

    ensureSslCert($lanIp);
    $prefix = installNginxConfigWindows($root, $nginxExe, $lanIp);

    $nginxArgs = [$nginxExe, '-p', $prefix . '\\', '-c', 'conf/nginx.conf'];
    $nginxCmd  = fn(string ...$extra) => implode(' ', array_map('escapeshellarg', [...$nginxArgs, ...$extra]));

    // Stop a leftover instance from a previous run that was not shut down cleanly
    if (is_file("$prefix\\logs\\nginx.pid")) {
        shell_exec($nginxCmd('-s', 'stop') . ' 2>NUL');
        @unlink("$prefix\\logs\\nginx.pid");
    }

    exec($nginxCmd('-t') . ' 2>&1', $testOutput, $testStatus);
    if ($testStatus !== 0) {
        throw new RuntimeException("NGINX config test failed:\n" . implode("\n", $testOutput));
    }

    echo "▶️  Starting " . DTS_WIN_FASTCGI_POOL . " php-cgi FastCGI processes on ports "
        . DTS_WIN_FASTCGI_PORT . '-' . (DTS_WIN_FASTCGI_PORT + DTS_WIN_FASTCGI_POOL - 1) . "...\n";
    $processes = startPhpCgiPool();

    echo "▶️  Starting NGINX...\n";
    $processes[] = startBackground($nginxArgs, "$prefix\\logs\\stdout.log");

    echo "\n✅ $mode server is now powered by NGINX + php-cgi!\n";
    echo "   Local:   https://localhost:" . DTS_PORT . "\n";
    echo "   Network: https://$lanIp:" . DTS_PORT . "\n";
    echo "   ⚠️  Browsers will warn about the self-signed cert: Advanced → Proceed.\n";

    superviseWindows($processes, $withWorker, fn() => shell_exec($nginxCmd('-s', 'stop') . ' 2>NUL'));
    return true;
}

/**
 * Windows: keep the services running in the foreground until Ctrl+C.
 * The queue worker exits on purpose after 100 jobs / its memory cap (it cannot
 * re-exec itself without PCNTL), so it is restarted here; if any other service
 * dies, everything is stopped.
 *
 * @param resource[] $services
 */
function superviseWindows(array $services, bool $withWorker, ?callable $beforeStop = null): void {
    $worker = $withWorker ? startQueueWorker() : null;

    $stopped = false;
    $shutdown = function () use (&$stopped, &$services, &$worker, $beforeStop) {
        if ($stopped) {
            return;
        }
        $stopped = true;
        echo "\n🛑 Stopping services...\n";
        if ($beforeStop) {
            $beforeStop();
        }
        stopBackground(array_filter([...$services, $worker]));
        echo "✅ Stopped cleanly. Goodbye!\n";
    };
    register_shutdown_function($shutdown);
    if (function_exists('sapi_windows_set_ctrl_handler')) {
        sapi_windows_set_ctrl_handler(function () use ($shutdown) {
            $shutdown();
            exit(0);
        });
    }

    echo "   Press Ctrl+C to stop.\n\n";

    while (true) {
        sleep(2);
        foreach ($services as $service) {
            if (!(proc_get_status($service)['running'] ?? false)) {
                echo "⚠️  A server process exited unexpectedly; check storage/logs and storage/nginx/logs.\n";
                $shutdown();
                exit(1);
            }
        }
        if ($worker && !(proc_get_status($worker)['running'] ?? false)) {
            proc_close($worker);
            echo "🔁 Queue worker exited (job/memory limit); restarting it...\n";
            $worker = startQueueWorker();
        }
    }
}

/**
 * Windows fallback when NGINX is not installed: PHP's built-in server with the
 * public/router.php front controller (plain HTTP).
 */
function runWindowsBuiltinServer(bool $withWorker): void {
    echo 'ℹ️  NGINX not found (set NGINX_PATH in .env or unzip nginx to C:\nginx).' . "\n";
    echo "🚀 Falling back to the PHP built-in server on port " . DTS_PORT . " (HTTP only)...\n";
    echo "   Local:   http://localhost:" . DTS_PORT . "\n";

    $server = startBackground(
        [PHP_BINARY, '-S', '0.0.0.0:' . DTS_PORT, '-t', 'public', 'public/router.php'],
        projectRoot() . '\storage\logs\builtin-server.log'
    );
    echo "   Request log: storage/logs/builtin-server.log\n";

    superviseWindows([$server], $withWorker);
}
