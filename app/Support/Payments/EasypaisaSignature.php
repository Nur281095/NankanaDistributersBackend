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

    /**
     * Build the callback map string used for merchantHashedResp verification.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function buildResponseMapString(array $payload): string
    {
        $reference = (string) ($payload['orderRefNumber'] ?? $payload['orderRefNum'] ?? '');
        $responseCode = (string) ($payload['responseCode'] ?? '');
        $responseDesc = (string) ($payload['responseDesc'] ?? $payload['desc'] ?? '');
        $storeId = (string) ($payload['storeId'] ?? '');

        return 'orderRefNumber='.$reference
            .'&responseCode='.$responseCode
            .'&responseDesc='.$responseDesc
            .'&storeId='.$storeId;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function signResponse(array $payload, string $hashKey): string
    {
        return base64_encode(hash_hmac(
            'sha256',
            self::buildResponseMapString($payload),
            $hashKey,
            true,
        ));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function verifyResponse(array $payload, string $hashKey, string $providedHash): bool
    {
        if ($providedHash === '' || $hashKey === '') {
            return false;
        }

        return hash_equals(
            self::signResponse($payload, $hashKey),
            $providedHash,
        );
    }
}
