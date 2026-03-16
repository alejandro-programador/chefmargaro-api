<?php

namespace Database\Seeders;

use App\Models\Extra;
use Illuminate\Database\Seeder;

class ExtraSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get available branch IDs
        $branchIds = \App\Models\Branch::pluck('branch_id')->toArray();
        
        if (empty($branchIds)) {
            $this->command->warn('No branches found. Please run BranchSeeder first.');
            return;
        }

        $extras = [
            [
                'branch_id' => $branchIds[0] ?? 1,
                'title' => 'Queso Extra',
                'description' => 'Porción adicional de queso mozzarella',
                'price_eur' => 2.00,
                'quantity' => 1,
                'is_active' => true,
            ],
            [
                'branch_id' => $branchIds[0] ?? 1,
                'title' => 'Pepperoni Extra',
                'description' => 'Porción adicional de pepperoni',
                'price_eur' => 2.50,
                'quantity' => 1,
                'is_active' => true,
            ],
            [
                'branch_id' => $branchIds[0] ?? 1,
                'title' => 'Champiñones Extra',
                'description' => 'Porción adicional de champiñones',
                'price_eur' => 1.50,
                'quantity' => 1,
                'is_active' => true,
            ],
            [
                'branch_id' => $branchIds[1] ?? ($branchIds[0] ?? 1),
                'title' => 'Aceitunas Extra',
                'description' => 'Porción adicional de aceitunas',
                'price_eur' => 1.50,
                'quantity' => 1,
                'is_active' => true,
            ],
            [
                'branch_id' => $branchIds[1] ?? ($branchIds[0] ?? 1),
                'title' => 'Borde Relleno de Queso',
                'description' => 'Borde de la pizza relleno de queso',
                'price_eur' => 3.00,
                'quantity' => 1,
                'is_active' => true,
            ],
        ];

        foreach ($extras as $extra) {
            Extra::updateOrCreate(
                [
                    'title' => $extra['title'],
                    'branch_id' => $extra['branch_id']
                ],
                $extra
            );
        }
    }
}
