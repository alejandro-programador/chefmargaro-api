<?php

namespace Database\Seeders;

use App\Models\Combo;
use Illuminate\Database\Seeder;

class ComboSeeder extends Seeder
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

        $combos = [
            [
                'name' => 'Combo Familiar',
                'price_eur' => 25.00,
                'description' => '2 pizzas grandes + 2 refrescos + papas fritas',
                'is_active' => true,
                'branch_id' => $branchIds[0] ?? 1,
            ],
            [
                'name' => 'Combo Personal',
                'price_eur' => 12.00,
                'description' => '1 pizza personal + 1 refresco',
                'is_active' => true,
                'branch_id' => $branchIds[0] ?? 1,
            ],
            [
                'name' => 'Combo Pareja',
                'price_eur' => 18.00,
                'description' => '2 pizzas medianas + 2 refrescos',
                'is_active' => true,
                'branch_id' => $branchIds[1] ?? ($branchIds[0] ?? 1),
            ],
            [
                'name' => 'Combo Deluxe',
                'price_eur' => 35.00,
                'description' => '3 pizzas grandes + 3 refrescos + papas fritas + alitas',
                'is_active' => true,
                'branch_id' => $branchIds[1] ?? ($branchIds[0] ?? 1),
            ],
        ];

        foreach ($combos as $combo) {
            Combo::updateOrCreate(
                [
                    'name' => $combo['name'],
                    'branch_id' => $combo['branch_id']
                ],
                $combo
            );
        }
    }
}
