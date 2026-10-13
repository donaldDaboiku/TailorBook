<?php

$origins = array_map(
    static fn (mixed $origin): string => rtrim(trim((string) $origin), '/'),
    [
        'https://tailormate-web.onrender.com',
        'https://tailormate-landing.onrender.com',
        env('FRONTEND_URL'),
        env('LANDING_URL'),
        'http://localhost:5173',
        'http://127.0.0.1:5173',
    ],
);

return [

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_unique(array_filter($origins))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['Content-Disposition'],

    'max_age' => 0,

    'supports_credentials' => false,

];
