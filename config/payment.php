<?php

/*
|--------------------------------------------------------------------------
| Payment gateways
|--------------------------------------------------------------------------
|
| Secrets come from the environment only; they are never stored in the
| database or shown in the UI. "manual" lets the admin confirm bank
| transfers by hand and is the safe default until a gateway is configured.
|
*/

return [

    'default' => env('PAYMENT_GATEWAY', 'manual'),

    // How long a checkout stays payable.
    'payment_window_hours' => 24,

    'gateways' => [

        'xendit' => [
            'secret_key' => env('XENDIT_SECRET_KEY'),
            // "Callback verification token" from the Xendit dashboard (Settings → Webhooks).
            'callback_token' => env('XENDIT_CALLBACK_TOKEN'),
        ],

        'tripay' => [
            // Tripay dashboard → Merchant → detail (API key, private key, merchant code).
            'api_key' => env('TRIPAY_API_KEY'),
            'private_key' => env('TRIPAY_PRIVATE_KEY'),
            'merchant_code' => env('TRIPAY_MERCHANT_CODE'),
            // true = https://tripay.co.id/api-sandbox (no real money).
            'sandbox' => (bool) env('TRIPAY_SANDBOX', true),
        ],

        'manual' => [
            'instructions' => env('PAYMENT_MANUAL_INSTRUCTIONS', 'Transfer the exact amount and send the proof to our admin. Your plan is activated after we confirm the payment.'),
        ],

    ],

];
