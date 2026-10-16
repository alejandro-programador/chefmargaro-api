<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Services\XetuxOrderService;
use Illuminate\Support\Carbon;
use ReflectionMethod;
use Tests\TestCase;

class XetuxOrderIdTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_xetux_order_id_stays_inside_signed_int_range(): void
    {
        Carbon::setTestNow('2026-09-30 14:00:00');

        $id = $this->xetuxOrderId(14);

        $this->assertSame(609300014, $id);
        $this->assertLessThan(2147483648, $id);
        $this->assertGreaterThan(0, $id);
    }

    public function test_xetux_order_id_stays_in_range_on_the_latest_calendar_day(): void
    {
        Carbon::setTestNow('2039-12-31 23:59:59');

        $id = $this->xetuxOrderId(99999);

        $this->assertSame(912319999, $id);
        $this->assertLessThan(2147483648, $id);
    }

    public function test_order_keeps_tax_inclusive_total_and_writes_roll_notes(): void
    {
        config(['xetux.api_key' => null]);

        $order = new Order(['total_amount' => 7]);
        $order->order_id = 14;
        $customer = new Customer(['name' => 'Ana Perez', 'email' => 'ana@example.com']);
        $customer->customer_id = 3;

        $payload = app(XetuxOrderService::class)->buildPayload($order, $customer, [[
            'type' => 'combo',
            'combo_id' => 9,
            'name' => 'Combo Pequeñin',
            'xetux_product_id' => 46,
            'quantity' => 1,
            'unit_price' => 7,
            'combinaciones' => [],
            'rollsConQuesoCremaCebollin' => false,
            'rollsConSesamo' => false,
        ]]);

        $sent = $payload['orders'][0];
        $this->assertEquals(7, $sent['subtotal']);
        $this->assertEquals(0, $sent['tax']);
        $this->assertEquals(7, $sent['total']);
        $this->assertStringContainsString('Hacer el roll sin cebollín ni queso crema.', $sent['notes']);
        $this->assertStringContainsString('Roll frío sin sésamo.', $sent['notes']);
        $this->assertStringContainsString('Hacer el roll sin cebollín ni queso crema.', $sent['body'][0]['notes']);
        $this->assertStringContainsString('Roll frío sin sésamo.', $sent['body'][0]['notes']);
    }

    private function xetuxOrderId(int $localOrderId): int
    {
        $service = app(XetuxOrderService::class);
        $method = new ReflectionMethod($service, 'generateXetuxOrderId');
        $method->setAccessible(true);

        return $method->invoke($service, $localOrderId);
    }
}
