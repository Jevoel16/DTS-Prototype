<?php

if (!function_exists('e')) {
    /**
     * Escape HTML special characters in a string for safe output rendering (XSS mitigation).
     *
     * @param string|null $value
     * @param bool $doubleEncode
     * @return string
     */
    function e(?string $value, bool $doubleEncode = true): string
    {
        if ($value === null) {
            return '';
        }
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', $doubleEncode);
    }
}

if (!function_exists('refuse_in_production')) {
    /**
     * Stop a destructive dev/demo CLI script (seeders, tamper demo, load tests) when APP_ENV=production.
     * Requires BASE_PATH to be defined.
     */
    function refuse_in_production(): void
    {
        $config = require BASE_PATH . '/src/Config/config.php';
        if (($config['app']['env'] ?? 'local') === 'production') {
            fwrite(STDERR, "Refusing to run " . basename($_SERVER['SCRIPT_NAME'] ?? 'this script') . " with APP_ENV=production.\n");
            exit(1);
        }
    }
}

if (!function_exists('render_error_page')) {
    /**
     * Send a small styled error page (403, 404, 429...) with a way back, then stop.
     */
    function render_error_page(int $code, string $title, string $message): never
    {
        http_response_code($code);
        header('Content-Type: text/html; charset=UTF-8');
        $home = isset($_SESSION['user_id']) ? '/dashboard' : '/';
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
            . '<title>' . e($title) . ' — DepEd DTS</title>'
            . '<link href="/css/tailwind.css" rel="stylesheet"></head>'
            . '<body class="font-sans antialiased bg-gray-100 text-gray-900 min-h-screen flex items-center justify-center p-4">'
            . '<main class="bg-white shadow-sm rounded-lg p-6 sm:p-8 max-w-md w-full text-center">'
            . '<p class="text-sm font-semibold text-gray-500">Error ' . $code . '</p>'
            . '<h1 class="mt-2 text-2xl font-bold">' . e($title) . '</h1>'
            . '<p class="mt-3 text-gray-600">' . e($message) . '</p>'
            . '<a href="' . $home . '" class="mt-6 inline-block px-4 py-2 rounded-md bg-accent-1 hover:bg-accent-1-hover text-white text-sm font-semibold">Go back to the start page</a>'
            . '</main></body></html>';
        exit;
    }
}

if (!function_exists('safe_back')) {
    /**
     * Redirect target for "go back": the Referer's path, but only when it points at this site.
     * Prevents a forged Referer from turning redirects into an open redirect to another domain.
     *
     * @param string $fallback Local path used when there is no usable Referer
     * @return string
     */
    function safe_back(string $fallback = '/'): string
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        if ($referer === '') {
            return $fallback;
        }

        $parts = parse_url($referer);
        $host = $parts['host'] ?? null;
        $requestHost = parse_url('//' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST);
        if ($parts === false || ($host !== null && strcasecmp($host, (string) $requestHost) !== 0)) {
            return $fallback;
        }

        $path = $parts['path'] ?? '/';
        // Reject protocol-relative or backslash tricks such as "//evil.com" or "/\evil.com"
        if (!str_starts_with($path, '/') || str_starts_with($path, '//') || str_contains($path, '\\')) {
            return $fallback;
        }

        return $path . (isset($parts['query']) ? '?' . $parts['query'] : '');
    }
}

if (!function_exists('has_error')) {
    /**
     * Check if a specific form field triggered a validation or authentication error.
     * This allows UI components to conditionally render error messages and apply error styling.
     *
     * @param string $key
     * @return bool
     */
    function has_error(string $key): bool
    {
        return isset($_SESSION['field_errors'][$key]);
    }
}

if (!function_exists('old')) {
    /**
     * Retrieve flashed user input for form state retention.
     * If the field contains a validation error, an empty string is returned so the user
     * can re-enter fresh data without submitting invalid state again.
     *
     * @param string $key
     * @param string $default
     * @return string
     */
    function old(string $key, string $default = ''): string
    {
        // If the field triggered an error constraint, clear it out so user re-types fresh
        if (has_error($key)) {
            return '';
        }
        $val = $_SESSION['old'][$key] ?? $default;
        return htmlspecialchars((string)$val, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('error_msg')) {
    /**
     * Retrieve the sanitized error message associated with a form field.
     *
     * @param string $key
     * @return string
     */
    function error_msg(string $key): string
    {
        return htmlspecialchars($_SESSION['field_errors'][$key] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('field_error_class')) {
    /**
     * Generate dynamic CSS classes for form inputs based on validation status.
     * Provides visual feedback by switching to red highlight styles when a field fails validation.
     *
     * @param string $key
     * @param string $defaultClass
     * @param string $errorClass
     * @return string
     */
    function field_error_class(
        string $key,
        string $defaultClass = 'border-gray-300 dark:border-gray-600',
        string $errorClass = 'border-red-500 ring-2 ring-red-500/20 dark:border-red-500'
    ): string {
        return has_error($key) ? $errorClass : $defaultClass;
    }
}

if (!function_exists('clear_form_flash')) {
    /**
     * Clear form state retention and error flash data from the session.
     * Called after form views render to prevent old input/errors from bleeding into subsequent requests.
     *
     * @return void
     */
    function clear_form_flash(): void
    {
        unset($_SESSION['old'], $_SESSION['field_errors']);
    }
}
