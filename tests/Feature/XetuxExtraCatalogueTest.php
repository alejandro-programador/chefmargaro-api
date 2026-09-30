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
            'xetux.extra_family_ids' => [2, 27],
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
        $this->assertFalse($products->contains(
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
                        'description' => 'Rolls',
                        'priceNet' => '10',
                        'combo' => true,
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
