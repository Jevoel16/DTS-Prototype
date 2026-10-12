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
