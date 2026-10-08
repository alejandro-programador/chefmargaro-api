<?php

return [
    'catalogue_url' => env(
        'XETUX_CATALOGUE_URL',
        'http://chefmargaroprueba.xetux.net/posadmin-xs/api/WebCatalogue'
    ),
    'api_key' => env('XETUX_API_KEY'),
    'combo_family_ids' => [1, 7, 8, 9],

    // Productos incluidos en combos (sin costo): salsas gratuitas vs extras de salsa de pago (family 25).
    'included_sauce_family_ids' => [24],
    // Bebidas elegibles como incluidas en combos (aguas, té, jugos, refrescos).
    'included_drink_family_ids' => [2, 4, 5, 30, 31],

    // Un extra por familia: el cliente elige el sabor (producto) al agregarlo.
    'drink_extra_families' => [
        2 => ['title' => 'Agua', 'description' => 'Elige el agua.'],
        4 => ['title' => 'Té', 'description' => 'Elige el té.'],
        31 => ['title' => 'Refresco 1L', 'description' => 'Elige el refresco de 1 litro.'],
        30 => ['title' => 'Refresco 1.5L', 'description' => 'Elige el refresco de 1.5 litros.'],
        5 => ['title' => 'Jugos', 'description' => 'Elige el jugo.'],
    ],

    'send_url' => env(
        'XETUX_SEND_URL',
        'https://chefmargaroprueba.xetux.net.xetux.online/xspos/api/XPosXPedidos/Send'
    ),

    'key_xpedidos' => env('XETUX_API_KEY', '096fc2e4-66df-4d71-9b5a-5d3290d75d6d'),
    'key_xpos' => env('XETUX_KEY_XPOS', '0x06C902AA18266716BDF02E8541BCB9AC'),
    'xpos_order_number' => env('XETUX_XPOS_ORDER_NUMBER', '1'),

    'system_type_id' => 1,
    'payform_id' => 1,
    'tax_rate' => 0.16,
];
