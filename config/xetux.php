<?php

return [
    'catalogue_url' => env(
        'XETUX_CATALOGUE_URL',
        'http://chefmargaroprueba.xetux.net/posadmin-xs/api/WebCatalogue'
    ),
    'api_key' => env('XETUX_API_KEY'),
    'combo_family_ids' => [1, 7, 8, 9],
    'extra_family_ids' => [6, 10, 11, 13, 2, 3, 4, 5, 17],

    'send_url' => env(
        'XETUX_SEND_URL',
        'https://chefmargaroprueba.xetux.net.xetux.online/xspos/api/XPosXPedidos/Send'
    ),

    'key_xpedidos' => env('XETUX_API_KEY', '096fc2e4-66df-4d71-9b5a-5d3290d75d6d'),

    'system_type_id' => 1,
    'payform_id' => 1,
    'tax_rate' => 0.16,
];
