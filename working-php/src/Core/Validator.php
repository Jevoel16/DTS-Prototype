<?php

namespace App\Core;

class Validator
{
    /**
     * Validates an array of data against a set of rules.
     * 
     * @param array $data The data to validate (usually $_POST)
     * @param array $rules Associative array of field => 'required|email|min:5'
     * @param string $redirectUrl URL to redirect to on failure
     * @return array Validated and sanitized data
     */
    public static function validate(array $data, array $rules): array
    {
        $errors = [];
        $validatedData = [];

        foreach ($rules as $field => $ruleString) {
            $value = $data[$field] ?? null;
            $ruleSet = explode('|', $ruleString);

            foreach ($ruleSet as $rule) {
                if ($rule === 'required' && (is_null($value) || trim((string)$value) === '')) {
                    $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . " is required.";
                    break;
                }
                if ($rule === 'email' && trim((string)$value) !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . " must be a valid email.";
                }
                if (str_starts_with($rule, 'min:')) {
                    $min = (int) substr($rule, 4);
                    if (strlen((string)$value) < $min) {
                        $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . " must be at least {$min} characters.";
                    }
                }
            }
            
            if (is_string($value)) {
                $validatedData[$field] = trim((string)$value);
            } else {
                $validatedData[$field] = $value;
            }
        }

        return [$errors, $validatedData];
    }
}
