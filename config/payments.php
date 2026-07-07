<?php

return [

    'jazzcash' => [
        'merchant_id' => env('JAZZCASH_MERCHANT_ID'),
        'password' => env('JAZZCASH_PASSWORD'),
        'integrity_salt' => env('JAZZCASH_INTEGRITY_SALT'),
        'return_url' => env(
            'JAZZCASH_RETURN_URL',
            rtrim((string) env('APP_URL', 'http://localhost'), '/').'/api/v1/payments/callback/jazzcash',
        ),
        'form_url' => env(
            'JAZZCASH_FORM_URL',
            'https://sandbox.jazzcash.com.pk/CustomerPortal/transactionmanagement/merchantform/',
        ),
        'currency' => env('JAZZCASH_CURRENCY', 'PKR'),
        'version' => env('JAZZCASH_VERSION', '1.1'),
        'language' => env('JAZZCASH_LANGUAGE', 'EN'),
        'txn_type' => env('JAZZCASH_TXN_TYPE', 'MWALLET'),
        'expiry_hours' => (int) env('JAZZCASH_EXPIRY_HOURS', 72),
    ],

    'easypaisa' => [
        'store_id' => env('EASYPAISA_STORE_ID'),
        'hash_key' => env('EASYPAISA_HASH_KEY'),
        'postback_url' => env(
            'EASYPAISA_POSTBACK_URL',
            rtrim((string) env('APP_URL', 'http://localhost'), '/').'/api/v1/payments/callback/easypaisa',
        ),
        'form_url' => env(
            'EASYPAISA_FORM_URL',
            'https://easypaystg.easypaisa.com.pk/easypay/Index.jsf',
        ),
        'confirm_url' => env(
            'EASYPAISA_CONFIRM_URL',
            'https://easypaystg.easypaisa.com.pk/easypay/Confirm.jsf',
        ),
        'expiry_hours' => (int) env('EASYPAISA_EXPIRY_HOURS', 24),
        'auto_redirect' => env('EASYPAISA_AUTO_REDIRECT', '1'),
    ],

];
