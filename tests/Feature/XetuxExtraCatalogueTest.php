<?php

namespace Tests\Feature;

use App\Services\XetuxCatalogueService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class XetuxExtraCatalogueTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'xetux.api_key' => 'test-key',
            'xetux.catalogue_url' => 'https://xetux.test/catalog',
        ]);

        Schema::dropIfExists('extras');
        Schema::create('extras', function (Blueprint $table) {
            $table->increments('extra_id');
            $table->unsignedInteger('xetux_product_id')->nullable();
            $table->timestamps();
        });
    }

    public function test_extra_products_include_complementos_and_wakame_from_gateway_catalogue(): void
    {
        Http::fake([
            'xetux.test/*' => Http::response($this->gatewayCatalogue()),
        ]);

        $response = $this->getJson('/api/v1/xetux/catalogue/extra-products');

        $response->assertOk();
        $products = collect($response->json('data.products'));

        $wakame = $products->firstWhere('product_name', 'WAKAME 100GR');
        $this->assertNotNull($wakame);
        $this->assertSame(27, $wakame['product_id']);
        $this->assertSame(27, $wakame['family_id']);
        $this->assertSame('Complementos', $wakame['family_name']);
        $this->assertSame('Acompañantes/Complementos', $wakame['family_path']);
        $this->assertSame('XPROD200037', $wakame['item_code']);

        $this->assertTrue($products->contains(
            fn ($product) => $product['family_name'] === 'Complementos'
                && $product['product_name'] === 'WASABI Y GENGIBRE'
        ));
        $this->assertTrue($products->contains(
            fn ($product) => $product['family_name'] === 'Postres'
                && $product['product_name'] === 'BROWNIE CHOCO CHOCO X UNID'
        ));
        $ketchup = $products->firstWhere('product_id', 73);
        $this->assertNotNull($ketchup);
        $this->assertSame('EXTRA DE SALSA KETCHUP', $ketchup['product_name']);
        $this->assertSame('Extra Salsa', $ketchup['family_name']);
        $this->assertTrue($products->contains(
            fn ($product) => $product['product_name'] === '10 ROLLS ESPECIALES'
        ));
    }

    public function test_gateway_catalogue_maps_removable_ingredients(): void
    {
        Http::fake([
            'xetux.test/*' => Http::response($this->gatewayCatalogue()),
        ]);

        $catalogue = app(XetuxCatalogueService::class)->fetchCatalogue();
        $sesamo = collect($catalogue['removibleIngredientList'])
            ->firstWhere('productId', 24);

        $this->assertNotNull($sesamo);
        $this->assertSame('SESAMO', $sesamo['ingredientList'][0]['ingredientName']);
        $this->assertSame(133, $sesamo['ingredientList'][0]['ingredientId']);
    }

    public function test_roll_combination_options_come_from_catalogue_categories(): void
    {
        Http::fake([
            'xetux.test/*' => Http::response([
                'success' => true,
                'data' => [
                    'families' => [],
                    'products' => [
                        ['id' => '61', 'name' => 'MIXTOS', 'sku' => 'XPRO2509000024', 'familyId' => '1'],
                        ['id' => '53', 'name' => '10 ROLLS CAMARON-DINAMITA', 'sku' => 'XPRO2509000010', 'familyId' => '1'],
                        ['id' => '39', 'name' => '10 ROLL CAMARON - CANGREJO', 'sku' => 'XPRO2509000001', 'familyId' => '1'],
                        ['id' => '42', 'name' => '10 ROLLS ESPECIALES', 'sku' => 'XPRO2509000004', 'familyId' => '1'],
                        ['id' => '49', 'name' => '20 ROLL DINAMITA - CAMARON', 'sku' => 'XPRO2509000006', 'familyId' => '1'],
                        ['id' => '40', 'name' => '10 ROLL POLLO CRISPY Y DINAMITA', 'sku' => 'XPRO2509000002', 'familyId' => '1'],
                        ['id' => '41', 'name' => '10 ROLL PASTA DE CANGREJO - CAMARON', 'sku' => 'XPRO2509000003', 'familyId' => '1'],
                    ],
                    'categories' => [
                        ['id' => '1', 'name' => 'TEXTURA'],
                        ['id' => '2', 'name' => 'PROTEINA ROLLS ESPECIALES'],
                        ['id' => '3', 'name' => 'COMPLEMENTOS'],
                        ['id' => '5', 'name' => 'SABOR'],
                    ],
                    'additionals' => [
                        ['id' => '1', 'name' => 'FRIOS'],
                        ['id' => '14', 'name' => 'COMBINADO'],
                        ['id' => '3', 'name' => 'SOLO ATUN'],
                        ['id' => '8', 'name' => 'CON TODO'],
                        ['id' => '20', 'name' => 'CHOCOLATE'],
                    ],
                    'additionalCategories' => [
                        ['groupId' => '1', 'optionId' => '14', 'position' => 1],
                        ['groupId' => '1', 'optionId' => '1', 'position' => 2],
                        ['groupId' => '2', 'optionId' => '3', 'position' => 1],
                        ['groupId' => '3', 'optionId' => '8', 'position' => 1],
                        ['groupId' => '5', 'optionId' => '20', 'position' => 1],
                    ],
                ],
            ]),
        ]);

        $response = $this->getJson('/api/v1/xetux/catalogue/roll-combination-options');

        $response->assertOk();
        $this->assertSame(
            ['COMBINADO', 'FRIOS'],
            collect($response->json('data.texturas'))->pluck('name')->all()
        );
        $this->assertSame(['SOLO ATUN'], collect($response->json('data.proteinas'))->pluck('name')->all());
        $this->assertSame(['CON TODO'], collect($response->json('data.complementos'))->pluck('name')->all());
        $this->assertSame([
            '10 ROLL CAMARON - CANGREJO',
            '10 ROLL POLLO CRISPY Y DINAMITA',
            '10 ROLL PASTA DE CANGREJO - CAMARON',
            '10 ROLLS CAMARON-DINAMITA',
            '10 ROLLS ESPECIALES',
        ], collect($response->json('data.rolls'))->pluck('name')->all());
        $this->assertSame(39, $response->json('data.rolls.0.id'));
        $this->assertSame('XPRO2509000004', $response->json('data.rolls.4.sku'));
    }

    public function test_legacy_catalogue_shape_still_lists_wakame(): void
    {
        Http::fake([
            'xetux.test/*' => Http::response([
                'familyList' => [
                    ['familyId' => 27, 'familyName' => 'Complementos', 'path' => 'Acompañantes/Complementos'],
                ],
                'productList' => [
                    [
                        'productId' => 27,
                        'itemId' => 270,
                        'itemCode' => 'XPROD200037',
                        'productName' => 'WAKAME 100GR',
                        'productDescription' => 'WAKAME 100GR',
                        'familyId' => 27,
                        'productSalePriceBaseWithTax' => 2,
                    ],
                ],
            ]),
        ]);

        $response = $this->getJson('/api/v1/xetux/catalogue/extra-products');

        $response->assertOk();
        $wakame = collect($response->json('data.products'))->firstWhere('product_name', 'WAKAME 100GR');
        $this->assertNotNull($wakame);
        $this->assertSame(270, $wakame['item_id']);
        $this->assertSame('Complementos', $wakame['family_name']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function gatewayCatalogue(): array
    {
        return [
            'success' => true,
            'message' => 'ok',
            'data' => [
                'families' => [
                    [
                        'id' => '27',
                        'name' => 'Complementos',
                        'familyTree' => 'Acompañantes/Complementos',
                        'comboOnly' => false,
                    ],
                    [
                        'id' => '1',
                        'name' => 'Rolls',
                        'familyTree' => 'Rolls',
                        'comboOnly' => false,
                    ],
                    [
                        'id' => '6',
                        'name' => 'Postres',
                        'familyTree' => 'Postres',
                        'comboOnly' => false,
                    ],
                    [
                        'id' => '25',
                        'name' => 'Extra Salsa',
                        'familyTree' => 'Acompañantes/Extra Salsa',
                        'comboOnly' => false,
                    ],
                ],
                'products' => [
                    [
                        'id' => '27',
                        'name' => 'WAKAME 100GR',
                        'sku' => 'XPROD200037',
                        'familyId' => '27',
                        'description' => 'WAKAME 100GR',
                        'priceNet' => '1.72413793',
                        'combo' => false,
                        'comboOnly' => false,
                    ],
                    [
                        'id' => '88',
                        'name' => 'WASABI Y GENGIBRE',
                        'sku' => 'XPROD200088',
                        'familyId' => '27',
                        'description' => 'WASABI Y GENGIBRE',
                        'priceNet' => '0.5',
                        'combo' => false,
                        'comboOnly' => false,
                    ],
                    [
                        'id' => '1',
                        'name' => '10 ROLLS ESPECIALES',
                        'sku' => 'XROLL',
                        'familyId' => '1',
                        'description' => '10 ROLLS ESPECIALES',
                        'priceNet' => '10',
                        'combo' => true,
                        'comboOnly' => false,
                    ],
                    [
                        'id' => '76',
                        'name' => 'BROWNIE CHOCO CHOCO X UNID',
                        'sku' => 'XPBOD2603000010',
                        'familyId' => '6',
                        'description' => 'BROWNIE CHOCO CHOCO X UNID',
                        'priceNet' => '1.29',
                        'combo' => false,
                        'comboOnly' => false,
                    ],
                    [
                        'id' => '73',
                        'name' => 'EXTRA SALSA DE ANGUILA',
                        'sku' => 'XPRO2603000055',
                        'familyId' => '25',
                        'description' => 'EXTRA DE SALSA KETCHUP',
                        'priceNet' => '0.43',
                        'combo' => false,
                        'comboOnly' => false,
                    ],
                ],
                'removables' => [
                    ['id' => '133', 'name' => 'SESAMO', 'sku' => 'XMAT2512000080'],
                ],
                'productRemovables' => [
                    ['groupId' => 'ing-24', 'productId' => '24', 'removableId' => '133'],
                ],
            ],
        ];
    }
}
