<?php

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
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

        $customers = [
            [
                'email' => 'juan.perez@example.com',
                'name' => 'Juan Pérez',
                'branch_id' => $branchIds[0] ?? 1,
                'signup_date' => '2024-01-15',
            ],
            [
                'email' => 'maria.garcia@example.com',
                'name' => 'María García',
                'branch_id' => $branchIds[0] ?? 1,
                'signup_date' => '2024-02-01',
            ],
            [
                'email' => 'carlos.rodriguez@example.com',
                'name' => 'Carlos Rodríguez',
                'branch_id' => $branchIds[1] ?? ($branchIds[0] ?? 1),
                'signup_date' => '2024-02-10',
            ],
            [
                'email' => 'ana.martinez@example.com',
                'name' => 'Ana Martínez',
                'branch_id' => $branchIds[1] ?? ($branchIds[0] ?? 1),
                'signup_date' => '2024-02-20',
            ],
            [
                'email' => 'luis.lopez@example.com',
                'name' => 'Luis López',
                'branch_id' => $branchIds[2] ?? ($branchIds[0] ?? 1),
                'signup_date' => '2024-03-01',
            ],
        ];

        foreach ($customers as $customer) {
            Customer::updateOrCreate(
                ['email' => $customer['email']],
                $customer
            );
        }
    }
}
