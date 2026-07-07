<?php

namespace App\Support\Payments;

class JazzCashSignature
{
    /**
     * @param  array<string, scalar|null>  $fields
     */
    public static function generate(array $fields, string $integritySalt): string
    {
        $values = self::sortedFieldValues($fields);

        if ($values === []) {
            return '';
        }

        $message = $integritySalt.'&'.implode('&', $values);

        return strtoupper(hash_hmac('sha256', $message, $integritySalt));
    }

    /**
     * @param  array<string, scalar|null>  $fields
     */
    public static function verify(array $fields, string $integritySalt, string $providedHash): bool
    {
        if ($providedHash === '') {
            return false;
        }

        $expected = self::generate($fields, $integritySalt);

        return hash_equals($expected, strtoupper($providedHash));
    }

    /**
     * @param  array<string, scalar|null>  $fields
     * @return list<string>
     */
    private static function sortedFieldValues(array $fields): array
    {
        $filtered = [];

        foreach ($fields as $key => $value) {
            if (! is_string($key) || ! str_starts_with($key, 'pp_') || $key === 'pp_SecureHash') {
                continue;
            }

            if ($value === null || $value === '') {
                continue;
            }

            $filtered[$key] = (string) $value;
        }

        ksort($filtered, SORT_STRING);

        return array_values($filtered);
    }
}
