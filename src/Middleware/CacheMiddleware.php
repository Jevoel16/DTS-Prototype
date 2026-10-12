<?php

namespace App\Middleware;

class CacheMiddleware
{
    public function handle($ttl = 55)
    {
        // Only cache GET requests
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            return;
        }

        // Do not serve or create cache if there are pending flash messages
        if (isset($_SESSION['success']) || isset($_SESSION['error']) || isset($_SESSION['info'])) {
            return;
        }

        $ttl = (int)$ttl;
        // Include query parameters in URI
        $requestUri = $_SERVER['REQUEST_URI'];
        
        $userId = $_SESSION['user_id'] ?? 'guest';
        $prefix = "cache_" . ($userId === 'guest' ? 'guest' : "user_{$userId}");
        
        // Use md5 of URI
        $uriHash = md5($requestUri);
        $cacheDir = BASE_PATH . '/cache/responses';
        $cacheFile = $cacheDir . '/' . $prefix . '_' . $uriHash . '.html';

        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $ttl) {
            // Serve cache and exit
            $cachedOutput = file_get_contents($cacheFile);

            // DYNAMIC CSRF INJECTION:
            // Since we cache full HTML pages, the cached HTML contains the CSRF token of the user 
            // who originally triggered the cache. We MUST replace it with the current user's token 
            // before serving, otherwise their POST requests will fail with a 419 Page Expired.
            if (!empty($_SESSION['csrf_token'])) {
                $cachedOutput = preg_replace(
                    '/(<input\s+type="hidden"\s+name="csrf_token"\s+value=")[^"]*("\s*\/?>)/i',
                    '${1}' . $_SESSION['csrf_token'] . '${2}',
                    $cachedOutput
                );
                
                $cachedOutput = preg_replace(
                    '/(<meta\s+name="csrf-token"\s+content=")[^"]*("\s*\/?>)/i',
                    '${1}' . $_SESSION['csrf_token'] . '${2}',
                    $cachedOutput
                );
            }

            echo $cachedOutput;
            echo "\n<!-- Served from Response Cache (TTL: {$ttl}s) -->";
            exit;
        }

        // Start output buffering
        ob_start();

        // Register shutdown function to capture and save output
        register_shutdown_function(function () use ($cacheFile, $cacheDir) {
            $output = ob_get_clean();
            
            // Check headers to avoid caching redirects
            $headers = headers_list();
            foreach ($headers as $header) {
                if (stripos($header, 'Location:') === 0) {
                    echo $output;
                    return;
                }
            }

            // Only cache valid HTTP 200 responses
            $code = http_response_code();
            if ($code === false || $code === 200) {
                if (!is_dir($cacheDir)) {
                    mkdir($cacheDir, 0755, true);
                }
                file_put_contents($cacheFile, $output);
            }

            echo $output;
        });
    }
}
