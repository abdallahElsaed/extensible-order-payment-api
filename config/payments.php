<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Simulation outcomes
    |--------------------------------------------------------------------------
    |
    | The shipped gateways are simulated. Each returns a deterministic outcome
    | ("successful" or "failed") so both branches are testable without flakiness.
    |
    */

    'simulation' => [
        'credit_card' => [
            'outcome' => env('CREDIT_CARD_OUTCOME', 'successful'),
        ],
        'paypal' => [
            'outcome' => env('PAYPAL_OUTCOME', 'successful'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Gateway credentials
    |--------------------------------------------------------------------------
    |
    | Real gateways read their credentials from here (sourced from env). The
    | simulated gateways do not use these, but they document the wiring.
    |
    */

    'credentials' => [
        'credit_card' => [
            'key' => env('CREDIT_CARD_KEY'),
            'secret' => env('CREDIT_CARD_SECRET'),
        ],
        'paypal' => [
            'client_id' => env('PAYPAL_CLIENT_ID'),
            'secret' => env('PAYPAL_SECRET'),
        ],
    ],

];
