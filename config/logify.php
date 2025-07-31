<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Konfigurasi Logging Channel Kustom
    |--------------------------------------------------------------------------
    |
    | Di sini Anda dapat menentukan channel logging baru yang akan digunakan
    | untuk mencatat aktivitas dengan format yang spesifik.
    |
    */

    'channel' => [
        'name' => env('LOGIFY_CHANNEL_NAME', 'logify'),

        'driver' => 'daily',
        'path' => storage_path('logs/logify.log'),
        'level' => env('LOGIFY_CHANNEL_LEVEL', 'info'),
        'days' => 14,
        
        'formatter' => Monolog\Formatter\JsonFormatter::class,
        'formatter_with' => [
            'batchMode' => Monolog\Formatter\JsonFormatter::BATCH_MODE_NEWLINES,
            'appendNewline' => true
        ],
    ],
];