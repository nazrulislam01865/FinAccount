<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Separate Landing Admin Credential Seeder
    |--------------------------------------------------------------------------
    |
    | These values create/update one dedicated landing_admin_users record when
    | seeders are run. This account is separate from normal system users and
    | signs in only through /landing-admin, not /login.
    |
    */

    'enabled' => env('LANDING_ADMIN_SEED_ENABLED', true),

    'credentials' => [
        'name' => env('LANDING_ADMIN_NAME', 'Landing Page Admin'),
        'email' => env('LANDING_ADMIN_EMAIL'),
        'password' => env('LANDING_ADMIN_PASSWORD'),
    ],
];
