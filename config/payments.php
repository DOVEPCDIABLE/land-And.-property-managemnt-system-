<?php

return [
    'enabled' => env('PAYMENTS_ENABLED', false),
    'paystack' => ['secret' => env('PAYSTACK_SECRET_KEY'), 'url' => 'https://api.paystack.co'],
    'flutterwave' => ['secret' => env('FLUTTERWAVE_SECRET_KEY'), 'webhook_secret' => env('FLUTTERWAVE_WEBHOOK_SECRET'), 'url' => 'https://api.flutterwave.com/v3'],
    'eversend' => ['client_id' => env('EVERSEND_CLIENT_ID'), 'client_secret' => env('EVERSEND_CLIENT_SECRET'), 'webhook_secret' => env('EVERSEND_WEBHOOK_SECRET'), 'url' => 'https://api.eversend.co/v1'],
];
