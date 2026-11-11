<?php

use App\Support\Security\SecurityEnvironment;

$isProductionEnvironment = SecurityEnvironment::isProduction();
$isLocalEnvironment = SecurityEnvironment::isLocal();

return [

    /*
    |--------------------------------------------------------------------------
    | Security Headers
    |--------------------------------------------------------------------------
    |
    | Baseline headers attached to every response by SecurityHeaders middleware.
    | A controller that already set one of these headers keeps its value.
    |
    */

    'broadcast_test_rate_limit_per_minute' => max(
        1,
        (int) env('SECURITY_BROADCAST_TEST_RATE_LIMIT_PER_MINUTE', 5),
    ),

    'headers' => [
        'x_content_type_options' => 'nosniff',
        // SAMEORIGIN, not DENY: same-origin PDF previews are embedded via iframe.
        'x_frame_options' => 'SAMEORIGIN',
        'referrer_policy' => 'strict-origin-when-cross-origin',
        // QR scanner needs the camera; every other powerful feature stays denied.
        'permissions_policy' => 'camera=(self), microphone=(), geolocation=(), payment=(), usb=()',
    ],

    'hsts' => [
        // Emitted regardless of the request scheme; browsers ignore it over plain HTTP.
        'enabled' => SecurityEnvironment::flag(env('SECURITY_HSTS_ENABLED'), $isProductionEnvironment),
        // Middleware falls back to 31536000 when enabled with a non-positive value.
        'max_age' => max(0, (int) env('SECURITY_HSTS_MAX_AGE', 31536000)),
        'include_subdomains' => SecurityEnvironment::flag(env('SECURITY_HSTS_INCLUDE_SUBDOMAINS'), false),
    ],

    'csp' => [
        // enforce | report-only | off
        'mode' => SecurityEnvironment::cspMode(env('SECURITY_CSP_MODE')),
        // Production refuses `off` unless SECURITY_CSP_ALLOW_OFF is explicitly true.
        'allow_off_in_production' => SecurityEnvironment::flag(env('SECURITY_CSP_ALLOW_OFF'), false),
        // Must be a first-party collector: reports contain full document URLs, including
        // password-reset tokens carried in the path. A third-party collector would leak them.
        'report_uri' => env('SECURITY_CSP_REPORT_URI'),
        // Local dev tooling (Vite HMR, debugbar, Boost logger) injects inline scripts.
        // The env flag can only tighten this: dev allowances are never enabled outside local.
        'dev_allowances' => $isLocalEnvironment && SecurityEnvironment::flag(env('SECURITY_CSP_DEV_ALLOWANCES'), true),
        'dev_server_origin' => env('VITE_DEV_ORIGIN', 'http://localhost:5173'),

        'directives' => [
            'default-src' => ["'self'"],
            'base-uri' => ["'self'"],
            'object-src' => ["'none'"],
            'form-action' => ["'self'"],
            // Keeps same-origin PDF preview iframes working.
            'frame-ancestors' => ["'self'"],
            'script-src' => ["'self'", 'https://seeyu.doscom.org'],
            // 'unsafe-inline' is required by Vue :style bindings and the <style> element ProseMirror injects.
            'style-src' => ["'self'", "'unsafe-inline'", 'https://fonts.googleapis.com'],
            'font-src' => ["'self'", 'data:', 'https://fonts.gstatic.com'],
            // https: allows admin-entered external image URLs.
            'img-src' => ["'self'", 'data:', 'blob:', 'https:'],
            'connect-src' => ["'self'", 'https://seeyu.doscom.org'],
            // maps.google.com redirects its /maps?output=embed embed to www.google.com,
            // and the browser re-checks the redirect target against frame-src.
            'frame-src' => ["'self'", 'https://maps.google.com', 'https://www.google.com', 'https://www.youtube-nocookie.com'],
            'media-src' => ["'self'"],
            'worker-src' => ["'self'", 'blob:'],
        ],
    ],

];
