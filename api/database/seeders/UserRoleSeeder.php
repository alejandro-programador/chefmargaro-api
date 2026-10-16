<?php

namespace Database\Seeders;

use App\Models\UserRole;
use Illuminate\Database\Seeder;

class UserRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            [
                'role_name' => 'admin',
                'description' => 'Administrador con todos los permisos',
                'permissions' => 'all',
            ],
            [
                'role_name' => 'verifier',
                'description' => 'Verificador de pagos',
                'permissions' => 'verify_payments',
            ],
            [
                'role_name' => 'manager',
                'description' => 'Gerente con permisos limitados',
                'permissions' => 'manage_orders,manage_products',
            ],
        ];

        foreach ($roles as $role) {
            UserRole::updateOrCreate(
                ['role_name' => $role['role_name']],
                $role
            );
        }
    }
}
