<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
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

        $products = [
            [
                'name' => 'Pizza Margherita',
                'price_eur' => 10.50,
                'description' => 'Pizza clásica italiana con tomate, mozzarella y albahaca',
                'is_active' => true,
                'branch_id' => $branchIds[0] ?? 1,
            ],
            [
                'name' => 'Pizza Pepperoni',
                'price_eur' => 12.00,
                'description' => 'Pizza con pepperoni y queso mozzarella',
                'is_active' => true,
                'branch_id' => $branchIds[0] ?? 1,
            ],
            [
                'name' => 'Pizza Hawaiana',
                'price_eur' => 13.50,
                'description' => 'Pizza con jamón, piña y queso',
                'is_active' => true,
                'branch_id' => $branchIds[0] ?? 1,
            ],
            [
                'name' => 'Pizza Cuatro Quesos',
                'price_eur' => 14.00,
                'description' => 'Pizza con mozzarella, gorgonzola, parmesano y fontina',
                'is_active' => true,
                'branch_id' => $branchIds[1] ?? ($branchIds[0] ?? 1),
            ],
            [
                'name' => 'Pizza Vegetariana',
                'price_eur' => 11.50,
                'description' => 'Pizza con verduras frescas y queso',
                'is_active' => true,
                'branch_id' => $branchIds[1] ?? ($branchIds[0] ?? 1),
            ],
        ];

        foreach ($products as $product) {
            Product::updateOrCreate(
                [
                    'name' => $product['name'],
                    'branch_id' => $product['branch_id']
                ],
                $product
            );
        }
    }
}
