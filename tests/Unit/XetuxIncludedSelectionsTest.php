<?php

namespace Tests\Unit;

use App\Services\XetuxCatalogueService;
use App\Services\XetuxOrderService;
use Tests\TestCase;

class XetuxIncludedSelectionsTest extends TestCase
{
    public function test_merge_appends_sauces_and_drink_for_the_admin_order_view(): void
    {
        $service = new XetuxOrderService(new XetuxCatalogueService);

        $rows = $service->mergeIncludedSelections(
            [
                ['textura' => 'COMBINADO', 'proteina' => 'SOLO ATUN', 'complemento' => 'CON TODO'],
            ],
            [
                ['product_name' => 'SOYA', 'quantity' => 2, 'xetux_product_id' => 141],
                ['product_name' => 'ANGUILA', 'quantity' => 0, 'xetux_product_id' => 142],
            ],
            ['product_name' => 'COCA COLA 1.5 L', 'quantity' => 1, 'xetux_product_id' => 3]
        );

        $this->assertSame('COMBINADO', $rows[0]['textura']);
        $this->assertSame('Salsa', $rows[1]['textura']);
        $this->assertSame('SOYA', $rows[1]['proteina']);
        $this->assertSame('x2', $rows[1]['complemento']);
        $this->assertSame('Bebida', $rows[2]['textura']);
        $this->assertSame('COCA COLA 1.5 L', $rows[2]['proteina']);
        $this->assertCount(3, $rows);
    }
}
