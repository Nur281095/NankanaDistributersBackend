<?php

namespace App\Support\Payments;

class EasypaisaSignature
{
    /**
     * @var list<string>
     */
    private const REQUEST_FIELD_ORDER = [
        'amount',
        'autoRedirect',
        'emailAddr',
        'mobileNum',
        'orderRefNum',
        'paymentMethod',
        'postBackURL',
        'storeId',
    ];

    /**
     * @param  array<string, string|null>  $fields
     */
    public static function buildMapString(array $fields): string
    {
        $parts = [];

        foreach (self::REQUEST_FIELD_ORDER as $key) {
            $parts[] = $key.'='.($fields[$key] ?? '');
        }

        return implode('&', $parts);
    }

    /**
     * @param  array<string, string|null>  $fields
     */
    public static function encryptRequest(array $fields, string $hashKey): string
    {
        $mapString = self::buildMapString($fields);
        $encrypted = openssl_encrypt($mapString, 'AES-128-ECB', $hashKey, OPENSSL_RAW_DATA);

        if ($encrypted === false) {
            throw new \RuntimeException('Unable to encrypt Easypaisa request hash.');
        }

        return base64_encode($encrypted);
    }

    public static function amountMatches(string $expectedAmount, string $receivedAmount): bool
    {
        return bccomp(
            number_format((float) $expectedAmount, 2, '.', ''),
            number_format((float) $receivedAmount, 2, '.', ''),
            2,
        ) === 0;
    }
}
