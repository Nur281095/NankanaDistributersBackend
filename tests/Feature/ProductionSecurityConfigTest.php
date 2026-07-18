<?php

it('keeps production debug forced off in app config', function (): void {
    $contents = file_get_contents(config_path('app.php'));

    expect($contents)->toContain("env('APP_ENV') === 'production'")
        ->and($contents)->toContain('? false');
});

it('defaults secure session cookies on for production', function (): void {
    $contents = file_get_contents(config_path('session.php'));

    expect($contents)->toContain('SESSION_SECURE_COOKIE')
        ->and($contents)->toContain("env('APP_ENV') === 'production'");
});

it('documents production secure cookie and debug settings in env example', function (): void {
    $contents = file_get_contents(base_path('.env.example'));

    expect($contents)->toContain('SESSION_SECURE_COOKIE=false')
        ->and($contents)->toContain('SESSION_SECURE_COOKIE=true')
        ->and($contents)->toContain('APP_DEBUG=false');
});
