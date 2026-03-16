<?php

namespace Database\Seeders;

use App\Models\Payment;
use App\Models\PaymentVerification;
use Illuminate\Database\Seeder;

class PaymentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Skip if payments already exist
        if (Payment::count() > 0) {
            return;
        }

        // Get available order and user IDs
        $orderIds = \App\Models\Order::pluck('order_id')->toArray();
        $userIds = \App\Models\User::pluck('user_id')->toArray();

        if (empty($orderIds)) {
            $this->command->warn('No orders found. Please run OrderSeeder first.');
            return;
        }

        if (empty($userIds)) {
            $this->command->warn('No users found. Please run UserSeeder first.');
            return;
        }

        // Payment 1 - Pending
        $payment1 = Payment::create([
            'order_id' => $orderIds[0],
            'payment_method' => 'cash',
            'payment_status' => 'pending',
            'payment_date' => now()->subDays(5),
            'proof_image_url' => 'https://example.com/proof1.jpg',
        ]);

        // Payment 2 - Completed with verification
        $payment2 = Payment::create([
            'order_id' => $orderIds[1] ?? $orderIds[0],
            'payment_method' => 'transfer',
            'payment_status' => 'completed',
            'payment_date' => now()->subDays(3),
            'proof_image_url' => 'https://example.com/proof2.jpg',
        ]);

        PaymentVerification::updateOrCreate(
            [
                'payment_id' => $payment2->payment_id,
            ],
            [
                'verifier_id' => $userIds[1] ?? $userIds[0],
                'verification_status' => 'approved',
                'verification_date' => now()->subDays(3)->addHours(2),
                'notes' => 'Pago verificado y aprobado correctamente',
            ]
        );

        // Payment 3 - Pending
        $payment3 = Payment::create([
            'order_id' => $orderIds[2] ?? $orderIds[0],
            'payment_method' => 'cash',
            'payment_status' => 'pending',
            'payment_date' => now()->subDays(1),
            'proof_image_url' => null,
        ]);
    }
}
