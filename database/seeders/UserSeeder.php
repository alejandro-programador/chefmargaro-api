<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = [
            [
                'email' => 'admin@chefmargaro.com',
                'name' => 'Administrador',
                'password' => Hash::make('password123'),
                'password_hash' => Hash::make('password123'),
                'user_type' => 'admin',
                'role_id' => 1,
            ],
            [
                'email' => 'verifier@chefmargaro.com',
                'name' => 'Verificador de Pagos',
                'password' => Hash::make('password123'),
                'password_hash' => Hash::make('password123'),
                'user_type' => 'verifier',
                'role_id' => 2,
            ],
            [
                'email' => 'manager@chefmargaro.com',
                'name' => 'Gerente',
                'password' => Hash::make('password123'),
                'password_hash' => Hash::make('password123'),
                'user_type' => 'manager',
                'role_id' => 3,
            ],
        ];

        foreach ($users as $user) {
            User::updateOrCreate(
                ['email' => $user['email']],
                $user
            );
        }
    }
}
