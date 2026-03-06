<?php

return [
    'gateway' => [
        'url' => env('TELEGRAM_GATEWAY_URL', 'http://4af690bcc2b8.vps.myjino.ru:49211'),
        'timeout' => env('TELEGRAM_GATEWAY_TIMEOUT', 30),
        'token' => env('TELEGRAM_GATEWAY_TOKEN'),
    ],
    
    'accounts' => [
        'account1' => [
            'api_id' => env('TG_API_ID_1'),
            'api_hash' => env('TG_API_HASH_1'),
            'phone' => env('TG_PHONE_1'),
        ],
    ],
];