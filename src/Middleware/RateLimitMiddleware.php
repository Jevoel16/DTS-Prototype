<?php

namespace App\Middleware;

use App\Core\RateLimiter;

/**
 * Per-IP request throttle. Route config: RateLimitMiddleware::class . ':name,maxHits,windowSeconds'
 */
class RateLimitMiddleware
{
    public function handle($name = 'default', $max = 60, $window = 60)
    {
        $key = "route:{$name}:" . RateLimiter::clientIp();

        if (RateLimiter::hit($key, (int) $max, (int) $window)) {
            return;
        }

        $retryAfter = max(1, RateLimiter::availableIn($key));
        http_response_code(429);
        header('Retry-After: ' . $retryAfter);

        $wantsJson = str_starts_with(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/', '/api/')
            || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))
            || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

        if ($wantsJson) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Too many requests. Please try again later.']);
        } else {
            echo "429 Too Many Requests: please wait " . ceil($retryAfter / 60) . " minute(s) and try again.";
        }
        exit;
    }
}
