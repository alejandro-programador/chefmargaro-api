<?php

namespace App\Services;

use App\Models\Combo;
use App\Models\Extra;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class XetuxCatalogueService
{
    /** @var array<string, mixed>|null */
    protected ?array $fetchedCatalogue = null;

    /**
     * @return array{familyList: array, productList: array, categoryList: array, additionalCategoryByProductList: array, removibleIngredientList: array}
     */
    public function fetchCatalogue(): array
    {
        if ($this->fetchedCatalogue !== null) {
            return $this->fetchedCatalogue;
        }

        $apiKey = config('xetux.api_key');
        if (empty($apiKey)) {
            throw new RuntimeException('XETUX_API_KEY no está configurada en el servidor.');
        }

        $response = Http::timeout(30)
            ->acceptJson()
            ->get(config('xetux.catalogue_url'), ['apiKey' => $apiKey]);

        if (! $response->successful()) {
            throw new RuntimeException(
                'No se pudo obtener el catálogo de Xetux (HTTP '.$response->status().').'
            );
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            throw new RuntimeException('Respuesta inválida del catálogo de Xetux.');
        }

        return $this->fetchedCatalogue = $this->normalizeCatalogue($payload);
    }

    /**
     * Base del catálogo sin IVA. Xetux debe recibir priceNetSecondary, no el precio con IVA.
     *
     * @param  array<string, mixed>  $product
     */
    public function netPriceFromRaw(array $product): float
    {
        $secondary = $product['priceNetSecondary'] ?? null;
        if ($secondary !== null && $secondary !== '' && (float) $secondary > 0) {
            return round((float) $secondary, 2);
        }

        $net = $product['priceNet'] ?? null;
        if ($net !== null && $net !== '' && (float) $net > 0) {
            return round((float) $net, 2);
        }

        $withTax = $product['productSalePriceBaseWithTax'] ?? null;
        if ($withTax !== null && $withTax !== '' && (float) $withTax > 0) {
            $rate = (float) config('xetux.catalog_price_tax_rate', 0.16);

            return round(((float) $withTax) / (1 + $rate), 2);
        }

        return 0.0;
    }

    /**
     * Precio de venta con IVA para la web.
     * priceNet trae la base a precisión completa (7 / 1.16) y así el REF queda exacto (2, 7, 22).
     * Si solo existe priceNetSecondary, se usa esa base redondeada.
     *
     * @param  array<string, mixed>  $product
     */
    public function salePriceFromRaw(array $product): float
    {
        $rate = (float) config('xetux.catalog_price_tax_rate', 0.16);
        $precise = $product['priceNet'] ?? null;
        if ($precise !== null && $precise !== '' && (float) $precise > 0) {
            return round((float) $precise * (1 + $rate), 2);
        }

        $secondary = $product['priceNetSecondary'] ?? null;
        if ($secondary !== null && $secondary !== '' && (float) $secondary > 0) {
            return round((float) $secondary * (1 + $rate), 2);
        }

        $withTax = $product['productSalePriceBaseWithTax'] ?? null;
        if ($withTax !== null && $withTax !== '') {
            return round((float) $withTax, 2);
        }

        return 0.0;
    }

    /**
     * @param  array<string, mixed>|null  $catalogue
     */
    public function salePriceForProductId(int $productId, ?array $catalogue = null): ?float
    {
        if ($productId <= 0) {
            return null;
        }

        $catalogue ??= $this->fetchCatalogue();
        foreach ($catalogue['productList'] ?? [] as $product) {
            if ((int) ($product['productId'] ?? 0) !== $productId) {
                continue;
            }

            return round((float) ($product['productSalePriceBaseWithTax'] ?? 0), 2);
        }

        return null;
    }

    /**
     * Base sin IVA (priceNetSecondary) para enviar el producto a Xetux.
     *
     * @param  array<string, mixed>|null  $catalogue
     */
    public function netPriceForProductId(int $productId, ?array $catalogue = null): ?float
    {
        if ($productId <= 0) {
            return null;
        }

        $catalogue ??= $this->fetchCatalogue();
        foreach ($catalogue['productList'] ?? [] as $product) {
            if ((int) ($product['productId'] ?? 0) !== $productId) {
                continue;
            }

            if (array_key_exists('productSalePriceNet', $product) && $product['productSalePriceNet'] !== null && $product['productSalePriceNet'] !== '') {
                return round((float) $product['productSalePriceNet'], 2);
            }

            return $this->netPriceFromRaw($product);
        }

        return null;
    }

    /**
     * Sustituye el precio guardado por el del catálogo (ya con IVA) para la tienda.
     *
     * @param  iterable<int, Extra>  $extras
     */
    public function applyDisplayedPrices(iterable $extras): void
    {
        $list = collect($extras)->filter(fn ($extra) => $extra instanceof Extra)->values();
        if ($list->isEmpty()) {
            return;
        }

        try {
            $catalogue = $this->fetchCatalogue();
        } catch (RuntimeException) {
            return;
        }

        foreach ($list as $extra) {
            if ($extra->isDrinkFamily()) {
                $flavors = $this->flavorsForFamily((int) $extra->xetux_family_id, $catalogue);
                $extra->flavorOptions = $flavors;
                $min = collect($flavors)->min('price');
                if ($min !== null) {
                    $extra->price_eur = round((float) $min, 2);
                }
                continue;
            }

            $price = $this->salePriceForProductId((int) $extra->xetux_product_id, $catalogue);
            if ($price !== null && $price > 0) {
                $extra->price_eur = $price;
            }
        }
    }

    /**
     * El precio publicado del combo es el del catálogo (base + IVA), no el guardado en la BD.
     *
     * @param  iterable<int, Combo>  $combos
     */
    public function applyDisplayedComboPrices(iterable $combos): void
    {
        $list = collect($combos)->filter(fn ($combo) => $combo instanceof Combo)->values();
        if ($list->isEmpty()) {
            return;
        }

        try {
            $catalogue = $this->fetchCatalogue();
        } catch (RuntimeException) {
            return;
        }

        foreach ($list as $combo) {
            $price = $this->salePriceForProductId((int) $combo->xetux_product_id, $catalogue);
            if ($price !== null && $price > 0) {
                $combo->price_eur = $price;
            }
        }
    }

    /**
     * El gateway actual responde { data: { families, products } }.
     * El resto del servicio sigue leyendo familyList / productList del POS anterior.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function normalizeCatalogue(array $payload): array
    {
        if (isset($payload['productList']) || isset($payload['familyList'])) {
            return $payload;
        }

        $data = isset($payload['products']) && is_array($payload['products'])
            ? $payload
            : ($payload['data'] ?? null);

        if (! is_array($data) || ! isset($data['products']) || ! is_array($data['products'])) {
            return $payload;
        }

        $families = collect($data['families'] ?? [])
            ->map(function ($family) {
                $id = (int) ($family['id'] ?? $family['familyId'] ?? 0);

                return [
                    'familyId' => $id,
                    'familyName' => (string) ($family['name'] ?? $family['familyName'] ?? ''),
                    'path' => (string) ($family['familyTree'] ?? $family['path'] ?? $family['name'] ?? ''),
                ];
            })
            ->filter(fn ($family) => $family['familyId'] > 0)
            ->unique('familyId')
            ->values()
            ->all();

        $products = collect($data['products'])
            ->map(function ($product) {
                $productId = (int) ($product['id'] ?? $product['productId'] ?? 0);

                return [
                    'productId' => $productId,
                    'itemId' => (int) ($product['itemId'] ?? $productId),
                    'itemCode' => $product['sku'] ?? $product['itemCode'] ?? null,
                    'productName' => (string) ($product['name'] ?? $product['productName'] ?? ''),
                    'productDescription' => $product['description'] ?? $product['productDescription'] ?? null,
                    'familyId' => (int) ($product['familyId'] ?? 0),
                    'productSalePriceNet' => $this->netPriceFromRaw($product),
                    'productSalePriceBaseWithTax' => $this->salePriceFromRaw($product),
                ];
            })
            ->filter(fn ($product) => $product['productId'] > 0)
            ->unique('productId')
            ->values()
            ->all();

        $removablesById = collect($data['removables'] ?? [])
            ->keyBy(fn ($removable) => (string) ($removable['id'] ?? ''));

        $ingredients = collect($data['productRemovables'] ?? [])
            ->groupBy(fn ($row) => (int) ($row['productId'] ?? 0))
            ->map(function ($rows, $productId) use ($removablesById) {
                return [
                    'productId' => (int) $productId,
                    'ingredientList' => collect($rows)->map(function ($row) use ($removablesById) {
                        $removableId = (string) ($row['removableId'] ?? '');
                        $removable = $removablesById->get($removableId);

                        return [
                            'ingredientId' => (int) ($row['removableId'] ?? 0),
                            'ingredientName' => (string) ($removable['name'] ?? ''),
                        ];
                    })->values()->all(),
                ];
            })
            ->filter(fn ($row) => $row['productId'] > 0)
            ->values()
            ->all();

        return array_merge($payload, [
            'familyList' => $families,
            'productList' => $products,
            'categoryList' => $data['categories'] ?? ($payload['categoryList'] ?? []),
            'removibleIngredientList' => $ingredients,
        ]);
    }

    /**
     * Piezas de 10 rolls que el cliente puede elegir en cada combinación.
     * El resto del catálogo de rolls (20, mixtos, etc.) no entra en este selector.
     *
     * @var array<int, string>
     */
    public const SELECTABLE_ROLL_NAMES = [
        '10 ROLL CAMARON - CANGREJO',
        '10 ROLL POLLO CRISPY Y DINAMITA',
        '10 ROLL PASTA DE CANGREJO - CAMARON',
        '10 ROLLS CAMARON-DINAMITA',
        '10 ROLLS ESPECIALES',
    ];

    /**
     * Texturas, rolls, proteínas de rolls especiales y complementos del catálogo Xetux.
     *
     * @return array{
     *     texturas: array<int, array{id: int, name: string}>,
     *     proteinas: array<int, array{id: int, name: string}>,
     *     complementos: array<int, array{id: int, name: string}>,
     *     rolls: array<int, array{id: int, name: string, sku: string}>
     * }
     */
    public function rollCombinationOptions(): array
    {
        $catalogue = $this->fetchCatalogue();
        $data = is_array($catalogue['data'] ?? null) ? $catalogue['data'] : [];

        $categories = collect($data['categories'] ?? $catalogue['categoryList'] ?? []);
        $additionals = collect($data['additionals'] ?? $catalogue['additionalList'] ?? [])
            ->keyBy(fn ($additional) => (string) ($additional['id'] ?? $additional['additionalId'] ?? ''));
        $links = collect($data['additionalCategories'] ?? $catalogue['additionalCategoryList'] ?? []);

        $buckets = [
            'texturas' => [],
            'proteinas' => [],
            'complementos' => [],
        ];

        $categoriesById = $categories->keyBy(fn ($category) => (string) ($category['id'] ?? $category['categoryId'] ?? ''));

        foreach ($links as $link) {
            $category = $categoriesById->get((string) ($link['groupId'] ?? $link['categoryId'] ?? ''));
            $categoryName = is_array($category)
                ? (string) ($category['name'] ?? $category['categoryName'] ?? '')
                : '';
            $bucket = $this->rollOptionBucket($categoryName);
            if ($bucket === null) {
                continue;
            }

            $additional = $additionals->get((string) ($link['optionId'] ?? $link['additionalId'] ?? ''));
            $name = is_array($additional)
                ? trim((string) ($additional['name'] ?? $additional['additionalName'] ?? ''))
                : trim((string) ($link['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $buckets[$bucket][] = [
                'id' => (int) ($additional['id'] ?? $link['optionId'] ?? 0),
                'name' => $name,
                'position' => (int) ($link['position'] ?? 0),
            ];
        }

        $options = collect($buckets)->map(function ($items) {
            return collect($items)
                ->unique('name')
                ->sortBy('position')
                ->map(fn ($item) => [
                    'id' => $item['id'],
                    'name' => $item['name'],
                ])
                ->values()
                ->all();
        })->all();

        $options['rolls'] = $this->selectableRollProducts(
            is_array($data['products'] ?? null) ? $data['products'] : []
        );

        // COMBINADO no es una textura que el cliente pueda elegir.
        $options['texturas'] = array_values(array_filter(
            $options['texturas'],
            fn ($item) => ! str_contains(
                $this->normalizeCatalogLabel((string) ($item['name'] ?? '')),
                'COMBINADO'
            )
        ));

        return $options;
    }

    /**
     * @param  array<int, mixed>  $products
     * @return array<int, array{id: int, name: string, sku: string}>
     */
    protected function selectableRollProducts(array $products): array
    {
        $wanted = [];
        foreach (self::SELECTABLE_ROLL_NAMES as $index => $name) {
            $wanted[$this->normalizeCatalogLabel($name)] = $index;
        }

        $matched = [];
        foreach ($products as $product) {
            if (! is_array($product)) {
                continue;
            }
            $name = trim((string) ($product['name'] ?? $product['productName'] ?? ''));
            $key = $this->normalizeCatalogLabel($name);
            if ($name === '' || ! array_key_exists($key, $wanted)) {
                continue;
            }
            $id = (int) ($product['id'] ?? $product['productId'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $familyId = (int) ($product['familyId'] ?? 0);
            if (isset($matched[$key]) && $familyId !== 1) {
                continue;
            }

            $matched[$key] = [
                'id' => $id,
                'name' => $name,
                'sku' => (string) ($product['sku'] ?? $product['itemCode'] ?? ''),
                'position' => $wanted[$key],
            ];
        }

        return collect($matched)
            ->sortBy('position')
            ->map(fn ($item) => [
                'id' => $item['id'],
                'name' => $item['name'],
                'sku' => $item['sku'],
            ])
            ->values()
            ->all();
    }

    protected function normalizeCatalogLabel(string $value): string
    {
        $value = strtoupper(trim($value));
        $value = strtr($value, [
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ñ' => 'N', 'Ü' => 'U',
            'á' => 'A', 'é' => 'E', 'í' => 'I', 'ó' => 'O', 'ú' => 'U', 'ñ' => 'N', 'ü' => 'U',
        ]);

        return trim((string) preg_replace('/[^A-Z0-9]+/', ' ', $value));
    }

    protected function rollOptionBucket(string $categoryName): ?string
    {
        $name = strtoupper(strtr($categoryName, [
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U',
            'á' => 'A', 'é' => 'E', 'í' => 'I', 'ó' => 'O', 'ú' => 'U',
        ]));

        if (str_contains($name, 'TEXTURA')) {
            return 'texturas';
        }
        if (str_contains($name, 'PROTEINA')) {
            return 'proteinas';
        }
        if (str_contains($name, 'COMPLEMENTO')) {
            return 'complementos';
        }

        return null;
    }

    /**
     * Productos Xetux permitidos para vincular combos (familias 1, 7, 8, 9).
     *
     * @return array<int, array<string, mixed>>
     */
    public function comboLinkableProducts(?int $excludeComboId = null): array
    {
        $linkedIds = $this->linkedXetuxProductIds(
            Combo::query()->whereNotNull('xetux_product_id'),
            $excludeComboId,
            'combo_id'
        );

        return $this->mapLinkableProducts(
            $this->fetchCatalogue(),
            config('xetux.combo_family_ids', [1, 7, 8, 9]),
            $linkedIds
        );
    }

    /**
     * Todos los productos del catálogo Xetux actual, sin lista fija de familias.
     *
     * @return array<int, array<string, mixed>>
     */
    public function extraLinkableProducts(?int $excludeExtraId = null): array
    {
        $linkedIds = $this->linkedXetuxProductIds(
            Extra::query()->whereNotNull('xetux_product_id'),
            $excludeExtraId,
            'extra_id'
        );

        return $this->mapLinkableProducts(
            $this->fetchCatalogue(),
            null,
            $linkedIds
        );
    }

    /**
     * Productos Xetux permitidos como incluidos en combos (salsas / bebidas).
     * No aplica unicidad: el mismo producto puede asociarse a varios combos.
     *
     * @return array{sauces: array<int, array<string, mixed>>, drinks: array<int, array<string, mixed>>}
     */
    public function includedLinkableProducts(): array
    {
        $catalogue = $this->fetchCatalogue();

        return [
            'sauces' => $this->mapLinkableProducts(
                $catalogue,
                config('xetux.included_sauce_family_ids', [24]),
                []
            ),
            'drinks' => $this->mapLinkableProducts(
                $catalogue,
                config('xetux.included_drink_family_ids', [2, 4, 5, 30, 31]),
                []
            ),
        ];
    }

    /**
     * Resuelve un producto del catálogo por productId (para validar asociaciones incluidas).
     *
     * @param  array<string, mixed>|null  $catalogue  Catálogo ya cargado (evita re-fetch en sync masivo)
     * @return array{product_id: int, item_id: int, family_id: int, product_name: string, type: string}|null
     */
    public function resolveIncludedProduct(int $xetuxProductId, ?array $catalogue = null): ?array
    {
        $catalogue ??= $this->fetchCatalogue();
        $sauceFamilies = config('xetux.included_sauce_family_ids', [24]);
        $drinkFamilies = config('xetux.included_drink_family_ids', [2, 4, 5, 30, 31]);
        $allowed = array_values(array_unique(array_merge($sauceFamilies, $drinkFamilies)));

        $product = collect($catalogue['productList'] ?? [])
            ->first(function ($p) use ($xetuxProductId, $allowed) {
                return (int) ($p['productId'] ?? 0) === $xetuxProductId
                    && in_array((int) ($p['familyId'] ?? 0), $allowed, true);
            });

        if (! $product) {
            return null;
        }

        $familyId = (int) ($product['familyId'] ?? 0);
        $type = in_array($familyId, $sauceFamilies, true) ? 'sauce' : 'drink';

        return [
            'product_id' => (int) ($product['productId'] ?? 0),
            'item_id' => (int) ($product['itemId'] ?? 0),
            'family_id' => $familyId,
            'product_name' => (string) ($product['productName'] ?? ''),
            'type' => $type,
        ];
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @return array<int, int>
     */
    protected function linkedXetuxProductIds($query, ?int $excludeId, string $primaryKey): array
    {
        if ($excludeId !== null) {
            $query->where($primaryKey, '!=', $excludeId);
        }

        return $query->pluck('xetux_product_id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * @param  array<int, int>|null  $allowedFamilies  null = todos los productos del catálogo
     * @param  array<int, int>  $linkedProductIds
     * @return array<int, array<string, mixed>>
     */
    protected function mapLinkableProducts(array $catalogue, ?array $allowedFamilies, array $linkedProductIds): array
    {
        $familiesById = collect($catalogue['familyList'] ?? [])
            ->unique('familyId')
            ->keyBy('familyId');

        return collect($catalogue['productList'] ?? [])
            ->filter(function ($product) use ($allowedFamilies) {
                if ($allowedFamilies === null) {
                    return true;
                }

                return in_array((int) ($product['familyId'] ?? 0), $allowedFamilies, true);
            })
            ->map(function ($product) use ($familiesById, $linkedProductIds) {
                $familyId = (int) ($product['familyId'] ?? 0);
                $productId = (int) ($product['productId'] ?? 0);
                $family = $familiesById->get($familyId);
                $description = trim((string) ($product['productDescription'] ?? ''));
                $name = trim((string) ($product['productName'] ?? ''));

                return [
                    'product_id' => $productId,
                    'item_id' => (int) ($product['itemId'] ?? 0),
                    'item_code' => $product['itemCode'] ?? null,
                    'product_name' => $description !== '' ? $description : $name,
                    'product_description' => $product['productDescription'] ?? null,
                    'family_id' => $familyId,
                    'family_name' => is_array($family) ? ($family['familyName'] ?? null) : null,
                    'family_path' => is_array($family) ? ($family['path'] ?? null) : null,
                    'price_usd' => (float) ($product['productSalePriceBaseWithTax'] ?? 0),
                    'is_linked' => in_array($productId, $linkedProductIds, true),
                ];
            })
            ->sortBy([
                ['family_name', 'asc'],
                ['product_name', 'asc'],
            ])
            ->values()
            ->all();
    }

    /**
     * Sabores (productos) de una familia de bebidas del catálogo actual.
     *
     * @param  array<string, mixed>|null  $catalogue
     * @return array<int, array{product_id: int, item_id: int, name: string, sku: string, price: float}>
     */
    public function flavorsForFamily(int $familyId, ?array $catalogue = null): array
    {
        if ($familyId <= 0 || ! array_key_exists($familyId, config('xetux.drink_extra_families', []))) {
            return [];
        }

        $catalogue ??= $this->fetchCatalogue();

        $items = collect($catalogue['productList'] ?? [])
            ->filter(fn ($product) => (int) ($product['familyId'] ?? 0) === $familyId)
            ->map(function ($product) use ($familyId) {
                $description = trim((string) ($product['productDescription'] ?? ''));
                $name = trim((string) ($product['productName'] ?? ''));
                $label = $description !== '' ? $description : $name;
                $productId = (int) ($product['productId'] ?? 0);
                $net = round((float) ($product['productSalePriceNet'] ?? $this->netPriceFromRaw($product)), 2);
                $price = round((float) ($product['productSalePriceBaseWithTax'] ?? 0), 2);
                $label = $this->drinkFlavorLabel($familyId, $label, $price, $productId);

                return [
                    'product_id' => $productId,
                    'item_id' => (int) ($product['itemId'] ?? $productId),
                    'label' => $label,
                    'sku' => trim((string) ($product['itemCode'] ?? '')),
                    'price' => $price,
                    'net_price' => $net,
                ];
            })
            ->filter(fn ($product) => $product['product_id'] > 0)
            ->unique('product_id')
            ->values();

        $items = $items->reject(function ($item) use ($items) {
            if (! str_starts_with((string) $item['sku'], 'XPBOD')) {
                return false;
            }
            $label = $this->normalizeCatalogLabel((string) $item['label']);

            return $items->contains(function ($other) use ($item, $label) {
                return (int) $other['product_id'] !== (int) $item['product_id']
                    && str_starts_with((string) $other['sku'], 'XPROD')
                    && $this->normalizeCatalogLabel((string) $other['label']) === $label
                    && (float) $other['price'] > (float) $item['price'] + 0.001;
            });
        })->values();

        $counts = $items->countBy('label');

        return $items
            ->map(function ($item) use ($counts) {
                $label = $item['label'];
                if (($counts[$label] ?? 0) > 1) {
                    $suffix = $item['sku'] !== '' ? $item['sku'] : '#'.$item['product_id'];
                    $label .= ' ('.$suffix.')';
                }

                return [
                    'product_id' => $item['product_id'],
                    'item_id' => $item['item_id'],
                    'name' => $label,
                    'sku' => $item['sku'],
                    'price' => $item['price'],
                    'net_price' => $item['net_price'],
                ];
            })
            ->sortBy('name')
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>|null  $catalogue
     * @return array{product_id: int, item_id: int, name: string, sku: string, price: float}|null
     */
    public function findFamilyFlavor(int $familyId, int $productId, ?array $catalogue = null): ?array
    {
        if ($productId <= 0) {
            return null;
        }

        foreach ($this->flavorsForFamily($familyId, $catalogue) as $flavor) {
            if ((int) $flavor['product_id'] === $productId) {
                return $flavor;
            }
        }

        return null;
    }

    /**
     * En Aguas, los productos a REF 2 siguen nombrados como Nevada 600 ml.
     * Ese precio es el del agua gasificada de 1.5L (la de 600 ml queda en REF 1).
     */
    protected function drinkFlavorLabel(int $familyId, string $label, float $price, int $productId): string
    {
        $label = $label !== '' ? $label : 'Producto '.$productId;
        if ($familyId !== 2 || abs($price - 2.0) > 0.02) {
            return $label;
        }

        $normalized = strtoupper(strtr($label, [
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ñ' => 'N',
            'á' => 'A', 'é' => 'E', 'í' => 'I', 'ó' => 'O', 'ú' => 'U', 'ñ' => 'N',
        ]));
        if (str_contains($normalized, 'GASIFIC')) {
            return $label;
        }

        return 'Agua gasificada 1.5L';
    }
}
