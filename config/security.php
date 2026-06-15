<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Security Headers
    |--------------------------------------------------------------------------
    |
    | These headers are intentionally compatible with the current Blade landing
    | page and accounting dashboard, which still use some inline styles/scripts.
    | Tighten script-src/style-src later after moving all inline code to assets.
    |
    */
    'headers' => [
        'enabled' => env('SECURITY_HEADERS_ENABLED', true),
        'frame_options' => env('SECURITY_FRAME_OPTIONS', 'SAMEORIGIN'),
        'referrer_policy' => env('SECURITY_REFERRER_POLICY', 'strict-origin-when-cross-origin'),
        'permissions_policy' => env('SECURITY_PERMISSIONS_POLICY', 'camera=(), microphone=(), geolocation=(), payment=()'),

        'hsts_enabled' => env('SECURITY_HSTS_ENABLED', false),
        'hsts' => env('SECURITY_HSTS', 'max-age=31536000; includeSubDomains; preload'),

        'csp_enabled' => env('SECURITY_CSP_ENABLED', true),
        'csp' => [
            'default-src' => ["'self'"],
            'base-uri' => ["'self'"],
            'object-src' => ["'none'"],
            'frame-ancestors' => ["'self'"],
            'form-action' => ["'self'"],
            'img-src' => ["'self'", 'data:', 'https:'],
            'font-src' => ["'self'", 'data:'],
            'style-src' => ["'self'", "'unsafe-inline'"],
            'script-src' => ["'self'", "'unsafe-inline'"],
            'connect-src' => ["'self'"],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Request Rate Limits
    |--------------------------------------------------------------------------
    */
    'rate_limits' => [
        'landing_inquiry_per_minute' => env('RATE_LIMIT_LANDING_INQUIRY_PER_MINUTE', 5),

        /*
         * Login throttles are failure-based and controlled from .env.
         * System login supports: email_ip, email, ip, global.
         * Landing Admin supports: username_ip, username, ip, global.
         * MAC address cannot be used by a web application because browsers do
         * not send the client's MAC address to the server.
         */
        'system_login' => [
            'enabled' => env('RATE_LIMIT_SYSTEM_LOGIN_ENABLED', true),
            'max_attempts' => env('RATE_LIMIT_SYSTEM_LOGIN_MAX_ATTEMPTS', 5),
            'lock_minutes' => env('RATE_LIMIT_SYSTEM_LOGIN_LOCK_MINUTES', 120),
            'key_strategy' => env('RATE_LIMIT_SYSTEM_LOGIN_KEY_STRATEGY', 'email_ip'),
        ],

        'landing_admin_login' => [
            'enabled' => env('RATE_LIMIT_LANDING_ADMIN_LOGIN_ENABLED', true),
            'max_attempts' => env('RATE_LIMIT_LANDING_ADMIN_LOGIN_MAX_ATTEMPTS', 5),
            'lock_minutes' => env('RATE_LIMIT_LANDING_ADMIN_LOGIN_LOCK_MINUTES', 120),
            'key_strategy' => env('RATE_LIMIT_LANDING_ADMIN_LOGIN_KEY_STRATEGY', 'username_ip'),
        ],

        /*
         * Kept for optional route-level throttles on public/non-login forms.
         */
        'landing_admin_login_per_minute' => env('RATE_LIMIT_LANDING_ADMIN_LOGIN_PER_MINUTE', 5),
        'web_forms_per_minute' => env('RATE_LIMIT_WEB_FORMS_PER_MINUTE', 30),
    ],
];
