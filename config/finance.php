<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Payment governance mode
    |--------------------------------------------------------------------------
    |
    | deny            — DenyAll (default; OD-009/010 unresolved)
    | allow_local     — Allow giving/event intents in local/manual provider mode
    | allow_configured— Allow listed purpose codes + currencies (still uses the
    |                   bound PaymentGateway; swap gateway when a vendor is chosen)
    |
    */
    'governance_mode' => env('PAYMENT_GOVERNANCE_MODE', 'deny'),

    'allowed_purpose_codes' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('PAYMENT_ALLOWED_PURPOSES', 'giving,event_payment')),
    ))),

    'allowed_currencies' => array_values(array_filter(array_map(
        static fn (string $code): string => strtoupper(trim($code)),
        explode(',', (string) env('PAYMENT_ALLOWED_CURRENCIES', 'NGN,USD,GBP')),
    ))),

    /*
    |--------------------------------------------------------------------------
    | Payment gateway driver
    |--------------------------------------------------------------------------
    |
    | local_manual — no external PSP; returns client checkout instructions and
    |                supports POST …/giving-intents/{id}/complete for local QA
    | none         — gateway initiate is a no-op (intent stays pending_provider)
    |
    */
    'gateway' => env('PAYMENT_GATEWAY', 'none'),

    'local_manual' => [
        'checkout_base_url' => env('PAYMENT_LOCAL_CHECKOUT_URL', env('APP_URL', 'http://localhost:8000').'/giving/checkout'),
    ],

    'callbacks' => [
        'web' => env('PAYMENT_WEB_CALLBACK_URL', env('FRONTEND_URL', 'http://localhost:3000').'/give/receipt'),
        'mobile' => env('PAYMENT_MOBILE_CALLBACK_URL', 'fhc://payments/success'),
    ],
];
