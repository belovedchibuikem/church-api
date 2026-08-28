<?php

$origins = array_values(array_filter(array_map(
    trim(...),
    explode(',', (string) env(
        'CORS_ALLOWED_ORIGINS',
        'http://localhost:3000,http://127.0.0.1:3000',
    )),
)));

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Browser auth uses cookies (`credentials: include`). The allow-origin
    | header must echo a specific frontend origin — never `*` — and
    | `supports_credentials` must be true. List local Next.js origins here
    | (and every production frontend origin via CORS_ALLOWED_ORIGINS,
    |  e.g. https://familyconnect-vert.vercel.app). Unknown origins get
    |  no Access-Control-Allow-Origin header.
    |
    */

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => $origins,

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
