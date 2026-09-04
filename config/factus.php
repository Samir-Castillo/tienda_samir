<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Factus API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the Factus electronic invoicing API (V1).
    | Docs: https://developers.factus.com.co/v1/
    |
    */

    'base_url' => env('FACTUS_API_URL', 'https://api-sandbox.factus.com.co'),

    'client_id' => env('FACTUS_CLIENT_ID'),

    'client_secret' => env('FACTUS_CLIENT_SECRET'),

    'username' => env('FACTUS_USERNAME'),

    'password' => env('FACTUS_PASSWORD'),

    'timeout' => env('FACTUS_TIMEOUT', 30),

    'endpoints' => [
        'token' => '/oauth/token',
        'bills_validate' => '/v1/bills/validate',
        'bills_show' => '/v1/bills/show',
        'numbering_ranges' => '/v1/numbering-ranges',
    ],

];
