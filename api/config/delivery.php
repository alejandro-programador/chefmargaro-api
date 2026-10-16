<?php

return [
    'tikket_base_url' => env('TIKKET_API_BASE_URL', 'https://api-serverless.tikket.net/api-3rd'),
    'tikket_channel_id' => env('TIKKET_CHANNEL_ID', '5_979515685241315'),
    'tikket_user_id' => env('TIKKET_USER_ID', 'ab694e27-ea60-48b0-a915-36fb91ba0cf5'),
    'location_api_url' => env(
        'DELIVERY_LOCATION_API_URL',
        'https://google-maps-khaki.vercel.app/api/location'
    ),
];
