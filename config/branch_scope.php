<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Branch scope header
    |--------------------------------------------------------------------------
    | When the client sends this header with a valid branch_id, list/show
    | responses are restricted to that branch (for admin / verificador).
    | Superadmin does not send the header and sees all data.
    |
    */
    'header_name' => env('BRANCH_SCOPE_HEADER', 'X-Branch-Id'),

    /*
    |--------------------------------------------------------------------------
    | Role slugs (for reference; permission checks are role-based in the app)
    |--------------------------------------------------------------------------
    */
    'roles' => [
        'superadmin' => [
            'slug' => 'superadmin',
            'sees_all_branches' => true,
        ],
        'admin' => [
            'slug' => 'admin',
            'sees_all_branches' => false,
        ],
        'verificador' => [
            'slug' => 'verificador',
            'sees_all_branches' => false,
            'permission_slugs' => ['orders', 'payments', 'customers'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Permission slugs used for verificador (orders, payments, customers)
    |--------------------------------------------------------------------------
    */
    'verificador_permission_slugs' => ['orders', 'payments', 'customers'],
];
