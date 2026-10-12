<?php

/**
 * Shared server setup helpers used by dev.php and prod.php.
 */

function executeCommand(string $command): void {
    passthru($command, $status);
    if ($status !== 0) {
        throw new RuntimeException("Command failed with exit code $status: $command");
    }
}

/**
 * Detect the machine's primary LAN IPv4 address (first non-loopback inet address).
 * Falls back to 127.0.0.1 if none is found (e.g. no network connection).
 */
function detectLanIp(): string {
    $output = shell_exec("ip -4 addr show scope global | grep -oP '(?<=inet )[\d.]+'");
    if ($output) {
        $ips = array_filter(array_map('trim', explode("\n", $output)));
        if (!empty($ips)) {
            return reset($ips);
        }
    }
    return '127.0.0.1';
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
    $certPath = '/etc/ssl/certs/dts_prototype.crt';
    $keyPath  = '/etc/ssl/private/dts_prototype.key';

    // Check if the existing cert already covers this LAN IP
    if (file_exists($certPath)) {
        $certInfo = shell_exec("openssl x509 -in $certPath -noout -text 2>/dev/null | grep -A1 'Subject Alternative Name'");
        if ($certInfo && str_contains($certInfo, "IP Address:$lanIp")) {
            echo "✅ SSL cert already valid for $lanIp — skipping regeneration.\n";
            return;
        }
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

    $cmd = "sudo openssl req -x509 -nodes -days 825 -newkey rsa:2048"
         . " -keyout $keyPath"
         . " -out $certPath"
         . " -config " . escapeshellarg($opensslCnf)
         . " 2>&1";

    executeCommand($cmd);
    unlink($opensslCnf);

    // Ensure the ssl-cert group can read the key
    executeCommand("sudo chgrp ssl-cert $keyPath");
    executeCommand("sudo chmod 640 $keyPath");

    echo "✅ SSL cert generated (valid 825 days, covers localhost + $lanIp).\n";
}

/**
 * Write the resolved NGINX site config from the project template,
 * substituting dynamic values, and ensure the sites-enabled symlink exists.
 */
function installNginxConfig(string $projectRoot, string $phpVersion, string $lanIp): void {
    $nginxTemplate = $projectRoot . '/nginx/site.conf';
    $nginxTarget   = '/etc/nginx/sites-available/dts-prototype';
    $nginxLink     = '/etc/nginx/sites-enabled/dts-prototype';

    $nginxConf = file_get_contents($nginxTemplate);
    $nginxConf = str_replace('__PROJECT_ROOT__', $projectRoot, $nginxConf);
    $nginxConf = str_replace('__PHP_VERSION__',  $phpVersion,  $nginxConf);
    $nginxConf = str_replace('__LAN_IP__',       $lanIp,       $nginxConf);

    // Write via sudo tee — /etc/nginx/sites-available/ is root-owned
    $escapedConf = escapeshellarg($nginxConf);
    executeCommand("echo $escapedConf | sudo tee $nginxTarget > /dev/null");

    if (!file_exists($nginxLink)) {
        executeCommand("sudo ln -s $nginxTarget $nginxLink");
    }

    echo "✅ NGINX site config installed from project (LAN IP: $lanIp).\n";
}
