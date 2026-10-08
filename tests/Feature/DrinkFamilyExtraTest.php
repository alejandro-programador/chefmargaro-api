<?php

namespace Tests\Feature;

use App\Models\Extra;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\XetuxCatalogueService;
use App\Services\XetuxOrderService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use RuntimeException;
use Tests\TestCase;

class DrinkFamilyExtraTest extends TestCase
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
            $table->string('title')->nullable();
            $table->decimal('price_eur', 10, 2)->default(0);
            $table->unsignedInteger('xetux_product_id')->nullable();
            $table->unsignedInteger('xetux_item_id')->nullable();
            $table->unsignedSmallInteger('xetux_family_id')->nullable();
            $table->timestamps();
        });

        Http::fake([
            'xetux.test/*' => Http::response($this->catalogue()),
        ]);
    }

    public function test_family_flavors_list_products_and_disambiguate_duplicate_names(): void
    {
        $flavors = app(XetuxCatalogueService::class)->flavorsForFamily(2);

        $nevada = collect($flavors)->filter(
            fn ($flavor) => str_contains($flavor['name'], 'AGUA NEVADA')
        )->values();

        $this->assertCount(2, $nevada);
        $this->assertSame('XPROD200001', $nevada[0]['sku']);
        $this->assertSame(0.86, $nevada[0]['price']);
        $this->assertTrue(collect($flavors)->contains(
            fn ($flavor) => $flavor['product_id'] === 2 && $flavor['name'] === 'AGUA MINALBA 600ML'
        ));
        $this->assertSame([], app(XetuxCatalogueService::class)->flavorsForFamily(99));
    }

    public function test_checkout_line_keeps_selected_flavor_for_xetux(): void
    {
        $extra = Extra::create([
            'title' => 'Agua',
            'price_eur' => 0.86,
            'xetux_family_id' => 2,
        ]);

        $service = app(XetuxOrderService::class);
        $lines = $service->resolveCartLines([[
            'type' => 'extra',
            'extra_id' => $extra->extra_id,
            'name' => 'Agua',
            'quantity' => 2,
            'unit_price' => 0.86,
            'xetux_product_id' => 2,
        ]]);

        $this->assertSame(2, $lines[0]['xetux_product_id']);
        $this->assertSame(2, $lines[0]['xetux_item_id']);
        $this->assertSame('Agua — AGUA MINALBA 600ML', $lines[0]['name']);
        $this->assertSame('flavor', $lines[0]['flavor']['kind']);
        $this->assertSame(2, $lines[0]['flavor']['xetux_product_id']);

        $context = (new ReflectionMethod(XetuxOrderService::class, 'promotionContext'))->invoke($service);
        $body = (new ReflectionMethod(XetuxOrderService::class, 'buildBodyLines'))
            ->invoke($service, $lines, $context);

        $this->assertSame(2, $body[0]['product']['id']);
        $this->assertSame('AGUA MINALBA 600ML', $body[0]['product']['name']);
        $this->assertSame(0.86, $body[0]['price']);
    }

    public function test_flavor_from_another_family_is_rejected(): void
    {
        $extra = Extra::create([
            'title' => 'Agua',
            'price_eur' => 0.86,
            'xetux_family_id' => 2,
        ]);

        $this->expectException(RuntimeException::class);

        app(XetuxOrderService::class)->resolveCartLines([[
            'type' => 'extra',
            'extra_id' => $extra->extra_id,
            'name' => 'Agua',
            'quantity' => 1,
            'unit_price' => 1.72,
            'xetux_product_id' => 14,
        ]]);
    }

    public function test_payment_accept_payload_uses_flavor_stored_on_the_order(): void
    {
        $extra = Extra::create([
            'title' => 'Agua',
            'price_eur' => 0.86,
            'xetux_family_id' => 2,
        ]);

        $item = new OrderItem([
            'extra_id' => $extra->extra_id,
            'quantity' => 1,
            'combinaciones' => [[
                'kind' => 'flavor',
                'product_name' => 'AGUA MINALBA 600ML',
                'xetux_product_id' => 2,
                'xetux_item_id' => 2,
                'unit_price' => 0.86,
            ]],
        ]);
        $item->setRelation('extra', $extra);

        $order = new Order;
        $order->setRelation('orderItems', collect([$item]));

        $service = app(XetuxOrderService::class);
        $stored = (new ReflectionMethod(XetuxOrderService::class, 'cartLinesFromOrderItems'))
            ->invoke($service, $order);
        $lines = $service->resolveCartLines($stored);
        $context = (new ReflectionMethod(XetuxOrderService::class, 'promotionContext'))->invoke($service);
        $body = (new ReflectionMethod(XetuxOrderService::class, 'buildBodyLines'))
            ->invoke($service, $lines, $context);

        $this->assertSame(2, $body[0]['id']);
        $this->assertSame('AGUA MINALBA 600ML', $body[0]['product']['name']);
        $this->assertSame('XPROD200002', $body[0]['product']['code']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function catalogue(): array
    {
        return [
            'success' => true,
            'data' => [
                'families' => [
                    ['id' => '2', 'name' => 'Aguas', 'familyTree' => 'Bebidas/Aguas'],
                    ['id' => '4', 'name' => 'Te', 'familyTree' => 'Bebidas/Te'],
                ],
                'products' => [
                    [
                        'id' => '1',
                        'name' => 'AGUA NEVADA 600 ML',
                        'description' => 'AGUA NEVADA 600 ML',
                        'sku' => 'XPROD200001',
                        'familyId' => '2',
                        'priceNet' => '0.86206897',
                    ],
                    [
                        'id' => '65',
                        'name' => 'AGUA NEVADA 600 ML',
                        'description' => 'AGUA NEVADA 600 ML',
                        'sku' => 'XPROD200065',
                        'familyId' => '2',
                        'priceNet' => '0.86206897',
                    ],
                    [
                        'id' => '2',
                        'name' => 'AGUA MINALBA 600ML',
                        'description' => 'AGUA MINALBA 600ML',
                        'sku' => 'XPROD200002',
                        'familyId' => '2',
                        'priceNet' => '0.86206897',
                    ],
                    [
                        'id' => '14',
                        'name' => 'LIPTON DURAZNO 500ML',
                        'description' => 'LIPTON DURAZNO 500ML',
                        'sku' => 'XPROD200014',
                        'familyId' => '4',
                        'priceNet' => '1.72413793',
                    ],
                ],
            ],
        ];
    }
}
