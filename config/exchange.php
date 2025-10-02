<?php
return [
    'default' => env('EXCHANGE_DEFAULT', 'upbit'),
    'upbit' => [
        'key' => env('UPBIT_API_KEY'),
        'secret' => env('UPBIT_API_SECRET'),
        'base' => env('UPBIT_BASE_URL', 'https://api.upbit.com'),
        'timeout' => 5,
    ],
];
