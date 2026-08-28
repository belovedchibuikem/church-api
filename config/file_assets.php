<?php

return [
    'maximum_bytes' => (int) env('FILE_ASSET_MAXIMUM_BYTES', 25 * 1024 * 1024),

    'allowed_mime_types' => [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/svg+xml',
        'image/x-icon',
        'image/vnd.microsoft.icon',
        'text/plain',
    ],
];
