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

    public function test_combo_body_nests_roll_modifiers_and_sauce_products(): void
    {
        $service = new XetuxOrderService(new XetuxCatalogueService);
        $roll = ['id' => 42, 'name' => '10 ROLLS ESPECIALES', 'sku' => 'XPRO2509000004', 'combo' => false];
        $soya = ['id' => 123, 'name' => 'SOYA', 'sku' => 'XPRO2607000137', 'combo' => false];
        $anguila = ['id' => 124, 'name' => 'ANGUILA', 'sku' => 'XPRO2607000139', 'combo' => false];
        $combo = ['id' => 46, 'name' => 'COMBO PEQUENIN 10 ROLSS', 'sku' => 'XPROM2509000004', 'combo' => true];

        $line = $service->mapComboBodyLine([
            'xetux_product_id' => 46,
            'name' => 'Pequeñin 10 Rolls',
            'quantity' => 1,
            'unit_price' => 7,
            'combinaciones' => [[
                'textura' => 'FRIOS',
                'proteina' => 'SOLO ATUN',
                'complemento' => 'SOLO PLATANO',
            ]],
            'included_sauces' => [
                ['product_name' => 'SOYA', 'quantity' => 1, 'xetux_product_id' => 141],
                ['product_name' => 'ANGUILA', 'quantity' => 1, 'xetux_product_id' => 142],
            ],
        ], [], [
            'productsById' => [46 => $combo, 42 => $roll, 123 => $soya, 124 => $anguila],
            'additionalsByName' => [
                'FRIOS' => ['id' => '1', 'name' => 'FRIOS', 'sku' => 'XMOD2509000001'],
                'SOLO ATUN' => ['id' => '3', 'name' => 'SOLO ATUN', 'sku' => 'XMOD2509000005'],
                'SOLO PLATANO' => ['id' => '9', 'name' => 'SOLO PLATANO', 'sku' => 'XMOD2509000009'],
            ],
            'categoryByAdditionalId' => [
                '1' => ['id' => 1, 'name' => 'TEXTURA'],
                '3' => ['id' => 2, 'name' => 'PROTEINA ROLLS ESPECIALES'],
                '9' => ['id' => 3, 'name' => 'COMPLEMENTOS'],
            ],
            'promotions' => [
                46 => [
                    'rolls' => [$roll],
                    'sauces' => [$soya, $anguila],
                    'drinks' => [],
                    'toppings' => [],
                ],
            ],
        ]);

        $this->assertTrue($line['product']['promo']);
        $this->assertSame('XPROM2509000004', $line['product']['code']);
        $this->assertSame('COMBO PEQUENIN 10 ROLSS', $line['product']['name']);
        $this->assertArrayNotHasKey('additionals', $line);
        $this->assertSame(42, $line['products'][0]['id']);
        $this->assertSame('FRIOS', $line['products'][0]['additionals'][0]['name']);
        $this->assertSame('TEXTURA', $line['products'][0]['additionals'][0]['categoryName']);
        $this->assertSame(123, $line['products'][1]['id']);
        $this->assertSame(124, $line['products'][2]['id']);
        $this->assertArrayNotHasKey('additionals', $line['products'][1]);
    }
}
