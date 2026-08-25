<?php

return [
    'rate_limits' => [
        'public_per_minute' => (int) env('PUBLIC_API_RATE_LIMIT_PER_MINUTE', 120),
    ],
];
