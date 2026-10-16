<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            BranchSeeder::class,
            PermissionSeeder::class,
            UserRoleSeeder::class,
            UserSeeder::class,
            CustomerSeeder::class,
            ProductSeeder::class,
            ExtraSeeder::class,
            ComboSeeder::class,
            OrderSeeder::class,
            PaymentSeeder::class,
        ]);
    }
}
