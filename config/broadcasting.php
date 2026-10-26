<?php

return [
    'default' => env('BROADCAST_CONNECTION', 'log'),
    'connections' => [
        'reverb' => [
            'driver' => 'reverb',
            'key' => env('REVERB_APP_KEY'),
            'secret' => env('REVERB_APP_SECRET'),
            'app_id' => env('REVERB_APP_ID'),
            'options' => [
                'host' => env('REVERB_HOST', 'localhost'),
                'port' => env('REVERB_PORT', 8080),
                'scheme' => env('REVERB_SCHEME', 'http'),
                'useTLS' => env('REVERB_SCHEME', 'http') === 'https',
                // The Pusher client sends this per request, overriding the Guzzle default below.
                'timeout' => (float) env('REVERB_TIMEOUT', 3),
            ],
            // Broadcasts run inside the request (ShouldBroadcastNow) and Reverb is on this PC,
            // so a hung Reverb must not hold the request for the 10 s / 30 s library defaults.
            'client_options' => [
                'connect_timeout' => (float) env('REVERB_CONNECT_TIMEOUT', 2),
                'timeout' => (float) env('REVERB_TIMEOUT', 3),
            ],
        ],
        'log' => ['driver' => 'log'],
        'null' => ['driver' => 'null'],
    ],
];
