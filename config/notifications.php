<?php
return [
    'default' => env('NOTIFICATIONS_PROVIDER', 'unisms'),

    'unisms' => [
        'base_url' => env('UNISMS_BASE_URL', 'https://unismsapi.com'),
        'api_key' => env('UNISMS_API_KEY', null),
        'sender_id' => env('UNISMS_SENDER_ID', null),
    ],
];
