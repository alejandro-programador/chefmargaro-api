<?php

namespace App\Services;

use App\Models\Combo;
use App\Models\Extra;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class XetuxCatalogueService
{
    /**
     * @return array{familyList: array, productList: array, categoryList: array, additionalCategoryByProductList: array, removibleIngredientList: array}
     */
    public function fetchCatalogue(): array
    {
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

        return $payload;
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
     * Productos Xetux permitidos para vincular extras.
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
            config('xetux.extra_family_ids', [6, 10, 11, 13, 2, 3, 4, 5, 17]),
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
     * @param  array<int, int>  $linkedProductIds
     * @return array<int, array<string, mixed>>
     */
    protected function mapLinkableProducts(array $catalogue, array $allowedFamilies, array $linkedProductIds): array
    {
        $familiesById = collect($catalogue['familyList'] ?? [])
            ->unique('familyId')
            ->keyBy('familyId');

        return collect($catalogue['productList'] ?? [])
            ->filter(fn ($product) => in_array((int) ($product['familyId'] ?? 0), $allowedFamilies, true))
            ->map(function ($product) use ($familiesById, $linkedProductIds) {
                $familyId = (int) ($product['familyId'] ?? 0);
                $productId = (int) ($product['productId'] ?? 0);
                $family = $familiesById->get($familyId);

                return [
                    'product_id' => $productId,
                    'item_id' => (int) ($product['itemId'] ?? 0),
                    'item_code' => $product['itemCode'] ?? null,
                    'product_name' => $product['productName'] ?? '',
                    'product_description' => $product['productDescription'] ?? null,
                    'family_id' => $familyId,
                    'family_name' => $family['familyName'] ?? null,
                    'family_path' => $family['path'] ?? null,
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
}
