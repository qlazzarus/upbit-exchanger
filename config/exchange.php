<?php
return [
    'default' => env('EXCHANGE_DEFAULT', 'upbit'),
    'upbit' => [
        'key' => env('UPBIT_API_KEY'),
        'secret' => env('UPBIT_API_SECRET'),
        'base' => env('UPBIT_BASE_URL', 'https://api.upbit.com'),
        'timeout' => 5,
        'min_quote_default' => [
            'KRW' => 5000,   // 업비트 KRW 마켓 최소 주문금액
        ],
        'orders_chance' => [
            'enabled' => true,
            'ttl_sec' => 3600, // 심볼별 메타 캐시 TTL (1시간)
        ],
    ],
];
