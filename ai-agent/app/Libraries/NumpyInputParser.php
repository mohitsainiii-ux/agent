<?php

namespace App\Libraries;

class NumpyInputParser
{
    public function parse(string $raw, array $numpySettings): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return ['success' => false, 'error' => 'Input is empty. Enter numbers such as 10, 20, 30, 40, 50.'];
        }

        $auto = ! empty($numpySettings['auto_array_conversion']);
        $parts = $auto
            ? preg_split('/[\s,;]+/', $raw) ?: []
            : explode(',', $raw);

        $values = [];
        foreach ($parts as $part) {
            $token = trim((string) $part);
            if ($token === '') {
                continue;
            }
            $values[] = $token;
        }

        if ($values === []) {
            return ['success' => false, 'error' => 'Input is empty. Enter numbers such as 10, 20, 30, 40, 50.'];
        }

        $max = (int) ($numpySettings['max_input_values'] ?? 10000);
        if (count($values) > $max) {
            return ['success' => false, 'error' => "Too many values. Maximum allowed is {$max}."];
        }

        $allowNegative = ! empty($numpySettings['allow_negative']);
        $allowDecimal  = ! empty($numpySettings['allow_decimal']);
        $numbers       = [];

        foreach ($values as $token) {
            if (! is_numeric($token)) {
                return ['success' => false, 'error' => 'Non-numeric values are not allowed. Use numbers only.'];
            }

            if (! $allowDecimal && str_contains($token, '.')) {
                return ['success' => false, 'error' => 'Decimal numbers are not allowed. Enable them in Settings or use whole numbers.'];
            }

            $number = (float) $token;

            if (! $allowNegative && $number < 0) {
                return ['success' => false, 'error' => 'Negative numbers are not allowed. Enable them in Settings or remove negative values.'];
            }

            $numbers[] = $number;
        }

        return ['success' => true, 'data' => $numbers];
    }
}
