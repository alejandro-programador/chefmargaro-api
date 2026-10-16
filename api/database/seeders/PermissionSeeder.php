<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            [
                'name' => 'Verificar Pagos',
                'slug' => 'verify_payments',
                'description' => 'Puede verificar comprobantes de pago',
            ],
            [
                'name' => 'Gestionar Órdenes',
                'slug' => 'manage_orders',
                'description' => 'Puede crear y actualizar órdenes',
            ],
            [
                'name' => 'Gestionar Productos',
                'slug' => 'manage_products',
                'description' => 'Puede crear, actualizar y eliminar productos',
            ],
            [
                'name' => 'Gestionar Usuarios',
                'slug' => 'manage_users',
                'description' => 'Puede gestionar usuarios del sistema',
            ],
            [
                'name' => 'Ver Reportes',
                'slug' => 'view_reports',
                'description' => 'Puede ver reportes y estadísticas',
            ],
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(
                ['slug' => $permission['slug']],
                $permission
            );
        }
    }
}
