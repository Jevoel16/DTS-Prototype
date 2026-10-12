<?php

require_once __DIR__ . '/server-lib.php';

// Populated once the Linux branch detects the PHP version
$GLOBALS['fpmService'] = 'php-fpm';

// PCNTL is unavailable on Windows (where prod mode exits below anyway)
if (extension_loaded('pcntl')) {
    // Enable asynchronous signal handling
    pcntl_async_signals(true);

    // Catch Ctrl+C / SIGTERM and stop services before exiting
    foreach ([SIGINT, SIGTERM] as $sig) {
        pcntl_signal($sig, function() {
            $fpmService = $GLOBALS['fpmService'];
            echo "\n🛑 Stopping production server...\n";
            shell_exec("sudo service nginx stop");
            shell_exec("sudo service $fpmService stop");
            shell_exec('pkill -f nginx');
            shell_exec('pkill -f php-fpm');
            echo "✅ NGINX & PHP-FPM stopped cleanly. Goodbye!\n";
            exit(0);
        });
    }
}

try {
    $os   = PHP_OS_FAMILY;
    $port = 8000;

    if ($os === 'Windows') {
        echo "❌ Production mode is not supported on Windows.\n";
        exit(1);
    }

    echo "🚀 [Production] Starting NGINX & PHP-FPM...\n";

    // Detect installed PHP version to target the correct FPM service
    $phpVersion = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
    $fpmService = "php{$phpVersion}-fpm";
    $GLOBALS['fpmService'] = $fpmService; // Make available to signal handler

    // Detect current LAN IP so the SSL cert and NGINX config cover it
    $lanIp = detectLanIp();
    echo "🌐 Detected LAN IP: $lanIp\n";

    echo "▶️  Restarting $fpmService to flush OPcache...\n";
    executeCommand("sudo service $fpmService restart");

    // Regenerate SSL cert if LAN IP has changed (or cert doesn't exist yet)
    ensureSslCert($lanIp);

    // Install the project NGINX config (substitutes placeholders)
    installNginxConfig(realpath(__DIR__ . '/..'), $phpVersion, $lanIp);

    echo "▶️  Ensuring NGINX is running...\n";
    executeCommand("sudo service nginx restart");

    echo "\n✅ Production Server is running!\n";
    echo "   Local:   https://localhost:$port\n";
    echo "   Network: https://$lanIp:$port\n";
    echo "   ⚠️  Other devices: open the URL, click Advanced → Accept the risk.\n\n";

    // In production the queue worker is managed externally (e.g. systemd / supervisor).
    // Keep this process alive so the composer script doesn't exit and stop services.
    echo "⏳ Keeping process alive. Press Ctrl+C to stop.\n";
    while (true) {
        pcntl_signal_dispatch();
        sleep(5);
    }

} catch (Throwable $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
