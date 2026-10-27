<?php

/*
|--------------------------------------------------------------------------
| Development Seed Accounts
|--------------------------------------------------------------------------
|
| Seeded account passwords are read from the environment and never
| hard-coded. Leave them empty to skip creating the seeded accounts.
|
*/

return [
    'admin' => [
        'email' => env('SEED_ADMIN_EMAIL', 'admin@example.com'),
        'password' => env('SEED_ADMIN_PASSWORD'),
    ],

    'demo' => [
        'email' => env('SEED_DEMO_EMAIL', 'demo@example.com'),
        'password' => env('SEED_DEMO_PASSWORD'),
    ],
];
