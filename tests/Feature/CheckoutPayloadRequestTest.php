<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CheckoutPayloadRequestTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('branches');
        Schema::create('branches', function (Blueprint $table) {
            $table->increments('branch_id');
            $table->string('name')->nullable();
        });
    }

    public function test_checkout_reads_order_fields_from_multipart_payload(): void
    {
        $response = $this->post('/api/v1/checkout', [
            'payload' => json_encode([
                'branch_id' => 99,
                'customer' => [
                    'name' => 'Ana Perez',
                    'email' => 'ana@correo.com',
                    'phone' => '04121234567',
                    'cedula' => '12345678',
                ],
                'delivery_type' => 'pickup',
                'total_amount' => 10,
                'cart_lines' => [[
                    'type' => 'extra',
                    'name' => 'Wasabi',
                    'quantity' => 1,
                    'unit_price' => 1,
                ]],
            ]),
            'payment_method' => 'mobile_payment',
            'reference_number' => '123456',
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(422);
        $errors = $response->json('errors') ?? [];

        foreach ([
            'customer',
            'customer.name',
            'customer.email',
            'delivery_type',
            'total_amount',
            'cart_lines',
        ] as $field) {
            $this->assertArrayNotHasKey($field, $errors);
        }

        $this->assertArrayHasKey('branch_id', $errors);
    }
}
