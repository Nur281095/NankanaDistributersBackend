<?php

$domain = env('APP_DOMAIN', 'nankanadistributors.com');
$scheme = env('APP_SCHEME', 'https');

return [

    /*
    |--------------------------------------------------------------------------
    | Primary domain
    |--------------------------------------------------------------------------
    |
    | Base domain for the business. Subdomains are built from this unless
    | overridden with explicit URL env vars.
    |
    */

    'domain' => $domain,

    'scheme' => $scheme,

    /*
    |--------------------------------------------------------------------------
    | Subdomains (production)
    |--------------------------------------------------------------------------
    */

    'subdomains' => [
        'api' => env('API_SUBDOMAIN', 'api'),
        'admin' => env('ADMIN_SUBDOMAIN', 'admin'),
        'www' => env('WWW_SUBDOMAIN', 'www'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Application URLs
    |--------------------------------------------------------------------------
    |
    | Always use official nankanadistributors.com URLs in .env (local + prod).
    | Local dev maps hostnames via /etc/hosts + Herd proxy — see docs/DOMAINS.md.
    |
    */

    'urls' => [
        'app' => rtrim((string) env(
            'APP_URL',
            sprintf('%s://%s.%s', $scheme, env('API_SUBDOMAIN', 'api'), $domain),
        ), '/'),

        'api' => rtrim((string) env(
            'API_URL',
            sprintf('%s://%s.%s/api/v1', $scheme, env('API_SUBDOMAIN', 'api'), $domain),
        ), '/'),

        'admin' => rtrim((string) env(
            'ADMIN_URL',
            sprintf('%s://%s.%s/admin', $scheme, env('ADMIN_SUBDOMAIN', 'admin'), $domain),
        ), '/'),

        'frontend' => rtrim((string) env(
            'FRONTEND_URL',
            sprintf('%s://%s', $scheme, $domain),
        ), '/'),
    ],

    /*
    |--------------------------------------------------------------------------
    | CORS / Sanctum (comma-separated in .env)
    |--------------------------------------------------------------------------
    */

    'cors_allowed_origins' => array_values(array_filter(array_map(
        trim(...),
        explode(',', (string) env('CORS_ALLOWED_ORIGINS', '')),
    ))),

    'sanctum_stateful_domains' => array_values(array_filter(array_map(
        trim(...),
        explode(',', (string) env('SANCTUM_STATEFUL_DOMAINS', '')),
    ))),

];
