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

    public function test_fuji_sauce_uses_combo_promotion_id_125(): void
    {
        $service = new XetuxOrderService(new XetuxCatalogueService);
        $soya = ['id' => 123, 'name' => 'SOYA', 'sku' => 'XPRO2607000137', 'combo' => false];
        $anguila = ['id' => 124, 'name' => 'ANGUILA', 'sku' => 'XPRO2607000139', 'combo' => false];
        $fuji = ['id' => 125, 'name' => 'SOYA', 'sku' => 'XPRO2607000141', 'combo' => false];

        $line = $service->mapComboBodyLine([
            'xetux_product_id' => 47,
            'name' => 'Combo Full 50 Rolls',
            'quantity' => 1,
            'unit_price' => 34,
            'combinaciones' => [],
            'included_sauces' => [
                ['product_name' => 'FUJI', 'quantity' => 1, 'xetux_product_id' => 143],
            ],
        ], [], [
            'productsById' => [
                47 => ['id' => 47, 'name' => 'COMBO FULL 50 ROLLS', 'sku' => 'XPROM2509000005', 'combo' => true],
            ],
            'additionalsByName' => [],
            'categoryByAdditionalId' => [],
            'promotions' => [
                47 => [
                    'rolls' => [],
                    'sauces' => [$soya, $anguila, $fuji],
                    'drinks' => [],
                    'toppings' => [],
                ],
            ],
        ]);

        $this->assertSame(125, $line['products'][0]['id']);
        $this->assertSame('FUJI', $line['products'][0]['product']['name']);
        $this->assertSame('XPRO2607000141', $line['products'][0]['product']['code']);
    }

    public function test_selected_roll_is_sent_and_protein_only_on_especiales(): void
    {
        $service = new XetuxOrderService(new XetuxCatalogueService);
        $camarones = ['id' => 39, 'name' => '10 ROLL CAMARON - CANGREJO', 'sku' => 'XPRO2509000001', 'combo' => false];
        $especiales = ['id' => 42, 'name' => '10 ROLLS ESPECIALES', 'sku' => 'XPRO2509000004', 'combo' => false];
        $combo = ['id' => 45, 'name' => 'COMBITO 20 ROLLS', 'sku' => 'XPROM2509000003', 'combo' => true];

        $line = $service->mapComboBodyLine([
            'xetux_product_id' => 45,
            'name' => 'Combito 20 Rolls',
            'quantity' => 1,
            'unit_price' => 11,
            'combinaciones' => [
                [
                    'textura' => 'FRIOS',
                    'roll' => '10 ROLL CAMARON - CANGREJO',
                    'roll_product_id' => 39,
                    'proteina' => '',
                    'complemento' => 'CON TODO',
                ],
                [
                    'textura' => 'COMBINADO',
                    'roll' => '10 ROLLS ESPECIALES',
                    'roll_product_id' => 42,
                    'proteina' => 'SOLO ATUN',
                    'complemento' => 'SOLO AGUACATE',
                ],
            ],
        ], [], [
            'productsById' => [45 => $combo, 39 => $camarones, 42 => $especiales],
            'additionalsByName' => [
                'FRIOS' => ['id' => '1', 'name' => 'FRIOS', 'sku' => 'XMOD2509000001'],
                'COMBINADO' => ['id' => '14', 'name' => 'COMBINADO', 'sku' => 'XMOD2509000014'],
                'SOLO ATUN' => ['id' => '3', 'name' => 'SOLO ATUN', 'sku' => 'XMOD2509000005'],
                'CON TODO' => ['id' => '8', 'name' => 'CON TODO', 'sku' => 'XMOD2509000010'],
                'SOLO AGUACATE' => ['id' => '7', 'name' => 'SOLO AGUACATE', 'sku' => 'XMOD2509000008'],
            ],
            'categoryByAdditionalId' => [
                '1' => ['id' => 1, 'name' => 'TEXTURA'],
                '14' => ['id' => 1, 'name' => 'TEXTURA'],
                '3' => ['id' => 2, 'name' => 'PROTEINA ROLLS ESPECIALES'],
                '8' => ['id' => 3, 'name' => 'COMPLEMENTOS'],
                '7' => ['id' => 3, 'name' => 'COMPLEMENTOS'],
            ],
            'promotions' => [
                45 => [
                    'rolls' => [$camarones, $especiales],
                    'sauces' => [],
                    'drinks' => [],
                    'toppings' => [],
                ],
            ],
        ]);

        $this->assertSame(39, $line['products'][0]['id']);
        $this->assertSame('10 ROLL CAMARON - CANGREJO', $line['products'][0]['product']['name']);
        $this->assertSame(
            ['FRIOS', 'CON TODO'],
            collect($line['products'][0]['additionals'])->pluck('name')->all()
        );

        $this->assertSame(42, $line['products'][1]['id']);
        $this->assertSame('10 ROLLS ESPECIALES', $line['products'][1]['product']['name']);
        $this->assertSame(
            ['COMBINADO', 'SOLO ATUN', 'SOLO AGUACATE'],
            collect($line['products'][1]['additionals'])->pluck('name')->all()
        );
        $this->assertSame('PROTEINA ROLLS ESPECIALES', $line['products'][1]['additionals'][1]['categoryName']);
        $this->assertStringContainsString('10 ROLL CAMARON - CANGREJO', $line['notes']);
        $this->assertStringContainsString('SOLO ATUN', $line['notes']);
    }
}
