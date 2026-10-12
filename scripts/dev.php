<?php

require_once __DIR__ . '/server-lib.php';

// Populated once the Linux branch detects the PHP version
$GLOBALS['fpmService'] = 'php-fpm';

// PCNTL is Linux-only; Windows Ctrl+C handling lives in server-lib.php
if (extension_loaded('pcntl')) {
    // Enable asynchronous signal handling
    pcntl_async_signals(true);

    // Catch Ctrl+C and kill the services before exiting
    pcntl_signal(SIGINT, function() {
        $fpmService = $GLOBALS['fpmService'];
        echo "\n🛑 Shutting down development server...\n";
        shell_exec("sudo service nginx stop");
        shell_exec("sudo service $fpmService stop");
        shell_exec('pkill -f nginx'); // Catch any escapees
        shell_exec('pkill -f php-fpm');
        echo "✅ NGINX & PHP-FPM stopped cleanly. Goodbye!\n";
        exit(0);
    });
}

try {
    $port = DTS_PORT;

    echo "🧹 Clearing application cache...\n";
    $cacheDir = __DIR__ . '/../cache';
    if (is_dir($cacheDir)) {
        $files = glob($cacheDir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        echo "✅ Local cache cleared.\n\n";
    }

    echo "💡 Tailwind is not rebuilt here: run `npm run dev` in another terminal while editing views.\n\n";

    if (isWindows()) {
        echo "🚀 [Windows Detected] Starting NGINX + php-cgi...\n";
        if (!runWindowsNginx(true, 'Development')) {
            runWindowsBuiltinServer(true);
        }
    } else {
        echo "🚀 [Linux Detected] Starting NGINX & PHP-FPM on demand...\n";

        // Detect installed PHP version to target the correct FPM service
        $phpVersion = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
        $fpmService = "php{$phpVersion}-fpm";
        $GLOBALS['fpmService'] = $fpmService; // Make available to SIGINT handler

        // Detect current LAN IP so the SSL cert and NGINX config cover it
        $lanIp = detectLanIp();
        echo "🌐 Detected LAN IP: $lanIp\n";

        echo "▶️  Restarting $fpmService to flush OPcache...\n";
        executeCommand("sudo service $fpmService restart");

        // Regenerate SSL cert if LAN IP has changed (or cert doesn't exist yet)
        ensureSslCert($lanIp);

        // Install the project NGINX config (substitutes placeholders)
        installNginxConfig(projectRoot(), $phpVersion, $lanIp);

        echo "▶️  Ensuring NGINX is running...\n";
        // Using restart so NGINX picks up the new PHP-FPM socket and updated cert
        executeCommand("sudo service nginx restart");

        startQueueWorker();

        echo "\n✅ Development Server is now powered by NGINX & PHP-FPM!\n";
        echo "   Local:   https://localhost:$port\n";
        echo "   Network: https://$lanIp:$port\n";
        echo "   ⚠️  Other devices: open the URL, click Advanced → Accept the risk.\n\n";
        echo "📋 Watching access logs...\n";

        executeCommand("tail -f /var/log/nginx/dts_access.log");
    }
} catch (Throwable $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
