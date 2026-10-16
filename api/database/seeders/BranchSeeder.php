<?php

namespace Database\Seeders;

use App\Models\Branch;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $branches = [
            [
                'name' => 'Sucursal Principal',
                'address' => 'Av. Principal 123, Caracas',
                'phone' => '0212-1234567',
            ],
            [
                'name' => 'Sucursal Este',
                'address' => 'Av. Este 456, Caracas',
                'phone' => '0212-7654321',
            ],
            [
                'name' => 'Sucursal Oeste',
                'address' => 'Av. Oeste 789, Caracas',
                'phone' => '0212-9876543',
            ],
        ];

        foreach ($branches as $branch) {
            Branch::updateOrCreate(
                ['name' => $branch['name']],
                $branch
            );
        }
    }
}
