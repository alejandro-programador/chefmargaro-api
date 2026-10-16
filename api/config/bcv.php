<?php

return [

    /*
    |--------------------------------------------------------------------------
    | BCV scraping
    |--------------------------------------------------------------------------
    |
    | Obtiene USD/EUR desde https://www.bcv.org.ve/
    |
    */

    'url' => env('BCV_URL', 'https://www.bcv.org.ve/'),

    'timeout' => (int) env('BCV_TIMEOUT', 30),

    'verify_ssl' => env('BCV_VERIFY_SSL', false),

    'cache_ttl' => (int) env('BCV_CACHE_TTL', 3600),

    'user_agent' => env(
        'BCV_USER_AGENT',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
    ),

];
