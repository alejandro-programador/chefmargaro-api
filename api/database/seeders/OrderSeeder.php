<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Skip if orders already exist
        if (Order::count() > 0) {
            return;
        }

        // Get available customer, product and combo IDs
        $customerIds = \App\Models\Customer::pluck('customer_id')->toArray();
        $productIds = \App\Models\Product::pluck('product_id')->toArray();
        $comboIds = \App\Models\Combo::pluck('combo_id')->toArray();

        if (empty($customerIds) || empty($productIds) || empty($comboIds)) {
            $this->command->warn('Missing required data. Please ensure customers, products and combos exist.');
            return;
        }

        // Order 1
        $order1 = Order::create([
            'customer_id' => $customerIds[0],
            'order_date' => now()->subDays(5),
            'total_amount' => 35.50,
            'payment_status' => 'pending',
            'delivery_type' => 'delivery',
            'order_status' => 'pending_payment',
        ]);

        OrderItem::create([
            'order_id' => $order1->order_id,
            'product_id' => $productIds[0],
            'combo_id' => null,
            'quantity' => 2,
        ]);

        OrderItem::create([
            'order_id' => $order1->order_id,
            'product_id' => null,
            'combo_id' => $comboIds[0],
            'quantity' => 1,
        ]);

        // Order 2
        $order2 = Order::create([
            'customer_id' => $customerIds[1] ?? $customerIds[0],
            'order_date' => now()->subDays(3),
            'total_amount' => 24.00,
            'payment_status' => 'completed',
            'delivery_type' => 'pickup',
            'order_status' => 'completed',
        ]);

        OrderItem::create([
            'order_id' => $order2->order_id,
            'product_id' => $productIds[1] ?? $productIds[0],
            'combo_id' => null,
            'quantity' => 2,
        ]);

        // Order 3
        $order3 = Order::create([
            'customer_id' => $customerIds[2] ?? $customerIds[0],
            'order_date' => now()->subDays(1),
            'total_amount' => 18.00,
            'payment_status' => 'pending',
            'delivery_type' => 'delivery',
            'order_status' => 'pending_payment',
        ]);

        OrderItem::create([
            'order_id' => $order3->order_id,
            'product_id' => null,
            'combo_id' => $comboIds[1] ?? $comboIds[0],
            'quantity' => 1,
        ]);
    }
}
