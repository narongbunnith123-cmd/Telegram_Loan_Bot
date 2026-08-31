<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Payment Gateway Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for payment gateway webhooks and API credentials.
    | Each gateway has its own webhook secret for signature validation.
    |
    */

    'gateways' => [
        'aba' => [
            'merchant_id' => env('ABA_MERCHANT_ID'),
            'api_key' => env('ABA_API_KEY'),
            'api_url' => env('ABA_API_URL', 'https://checkout-sandbox.payway.com.kh'),
            'webhook_url' => env('ABA_WEBHOOK_URL'),
            'webhook_secret' => env('ABA_API_KEY'), // Same key for webhook verification
            'default_currency' => 'USD',
        ],

        'khqr' => [
            'webhook_secret' => env('KHQR_WEBHOOK_SECRET'),
            'merchant_id' => env('KHQR_MERCHANT_ID'),
            'api_key' => env('KHQR_API_KEY'),
        ],

        'stripe' => [
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
            'api_key' => env('STRIPE_SECRET_KEY'),
            'publishable_key' => env('STRIPE_PUBLISHABLE_KEY'),
        ],

        'paypal' => [
            'webhook_secret' => env('PAYPAL_WEBHOOK_SECRET'),
            'client_id' => env('PAYPAL_CLIENT_ID'),
            'client_secret' => env('PAYPAL_CLIENT_SECRET'),
            'mode' => env('PAYPAL_MODE', 'sandbox'), // sandbox or live
        ],

        'simulated' => [
            // No configuration needed for simulated gateway
        ],
    ],

];
