<?php

namespace Tests\Feature;

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

    private function xetuxOrderId(int $localOrderId): int
    {
        $service = app(XetuxOrderService::class);
        $method = new ReflectionMethod($service, 'generateXetuxOrderId');
        $method->setAccessible(true);

        return $method->invoke($service, $localOrderId);
    }
}
