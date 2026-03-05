<?php

return [
    'accounts' => [
        'account1' => [
            'api_id' => env('TG_API_ID_1'),
            'api_hash' => env('TG_API_HASH_1'),
            'phone' => env('TG_PHONE_1'),
        ],
        'account2' => [
            'api_id' => env('TG_API_ID_2'),
            'api_hash' => env('TG_API_HASH_2'),
            'phone' => env('TG_PHONE_2'),
        ],
    ],
    // Настройки прокси
    'proxy' => [
        'enabled' => true,
        'test_url' => 'http://www.google.com/generate_204',
        'connection_timeout' => 5, // секунд
        'max_proxy_failures' => 3,
    ],
    
    // Настройки загрузки файлов
    'download' => [
        'timeout' => 300, // секунд на скачивание файла
        'max_size' => 20 * 1024 * 1024, // 20 MB
        'chunk_size' => 1024 * 1024, // 1 MB chunks
    ],    
];