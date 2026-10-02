<?php

namespace App\Services;

use App\Models\Combo;
use App\Models\Customer;
use App\Models\Extra;
use App\Models\Order;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class XetuxOrderService
{
    public function __construct(
        protected XetuxCatalogueService $catalogue
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $cartLines
     * @return array<string, mixed>
     */
    public function buildPayload(
        Order $order,
        Customer $customer,
        array $cartLines,
        array $checkoutMeta = []
    ): array {
        $xetuxOrderId = $this->generateXetuxOrderId($order->order_id);
        $tracking = $this->generateTrackingCode();
        $createdAt = now()->getTimestampMs();
        $subtotal = round((float) $order->total_amount, 2);
        $taxRate = (float) config('xetux.tax_rate', 0.16);
        $tax = round($subtotal * $taxRate, 2);
        $total = round($subtotal + $tax, 2);

        $nameParts = preg_split('/\s+/', trim($customer->name), 2);
        $firstName = $nameParts[0] ?? 'Cliente';
        $lastName = $nameParts[1] ?? '.';

        $notes = trim((string) ($checkoutMeta['notes'] ?? $order->notes ?? ''));
        if ($checkoutMeta['delivery_address'] ?? null) {
            $notes = trim($notes."\nEntrega: ".$checkoutMeta['delivery_address']);
        }
        if ($checkoutMeta['reference_point'] ?? null) {
            $notes = trim($notes."\nReferencia: ".$checkoutMeta['reference_point']);
        }

        $deliveryType = strtolower((string) ($checkoutMeta['delivery_type'] ?? $order->delivery_type ?? 'pickup'));
        $isPickup = $deliveryType !== 'delivery';
        $cedula = preg_replace('/\D+/', '', (string) ($checkoutMeta['cedula'] ?? '')) ?? '';

        return [
            'keyXpos' => (string) config('xetux.key_xpos'),
            'keyXpedidos' => config('xetux.key_xpedidos'),
            'xposOrderNumber' => (string) config('xetux.xpos_order_number', '1'),
            'orders' => [
                [
                    'id' => $xetuxOrderId,
                    'systemTypeId' => (int) config('xetux.system_type_id', 1),
                    'trackingNumber' => $tracking,
                    'trackingShort' => $tracking,
                    'notes' => $notes !== '' ? $notes : 'Pedido ecommerce Chef Margaro',
                    'payformId' => (int) config('xetux.payform_id', 1),
                    'pickupTypeId' => $isPickup ? 2 : 1,
                    'pickupTypeName' => $isPickup ? 'PickUp' : 'Delivery',
                    'createdAt' => $createdAt,
                    'client' => [
                        'id' => (int) ($customer->customer_id + 3000000),
                        'docType' => 'V',
                        'document' => $cedula,
                        'firstName' => $firstName,
                        'lastName' => $lastName,
                        'email' => $customer->email,
                        'phone' => (string) ($checkoutMeta['phone'] ?? ''),
                        'addressStreet' => (string) ($checkoutMeta['delivery_address'] ?? ''),
                        'addressHome' => (string) ($checkoutMeta['reference_point'] ?? ''),
                    ],
                    'subtotal' => $subtotal,
                    'tax' => $tax,
                    'total' => $total,
                    'totalAlternative' => $total,
                    'totalDiscount' => 0.0,
                    'tip' => 0.0,
                    'shippingCost' => 0.0,
                    'body' => $this->buildBodyLines($cartLines, $this->promotionContext()),
                    'paid' => false,
                    'billed' => false,
                ],
            ],
            'ordersCount' => 1,
            'dateSynch' => (int) now()->format('YmdHis'),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function send(array $payload): array
    {
        $response = Http::timeout(45)
            ->acceptJson()
            ->post(config('xetux.send_url'), $payload);

        if (! $response->successful()) {
            throw new RuntimeException(
                'Xetux rechazó el pedido (HTTP '.$response->status().'): '.$response->body()
            );
        }

        $json = $response->json();

        return is_array($json) ? $json : ['raw' => $response->body()];
    }

    /**
     * @param  array<int, array<string, mixed>>  $cartLines
     * @param  array<string, mixed>  $context
     * @return array<int, array<string, mixed>>
     */
    protected function buildBodyLines(array $cartLines, array $context): array
    {
        $lines = collect($cartLines);
        $combos = $lines->where('type', 'combo')->values();
        $extras = $lines->where('type', 'extra')->values();
        $products = $lines->where('type', 'product')->values();
        $body = [];

        foreach ($combos as $comboLine) {
            $comboId = (int) ($comboLine['combo_id'] ?? 0);
            $xetuxProductId = (int) ($comboLine['xetux_product_id'] ?? 0);
            if ($xetuxProductId <= 0) {
                throw new RuntimeException("El combo #{$comboId} no tiene producto Xetux vinculado.");
            }

            $comboExtras = $extras->filter(function ($extra) use ($comboId) {
                $parent = $extra['parent_combo_id'] ?? null;
                if ($parent !== null && (int) $parent === $comboId) {
                    return true;
                }
                $cartKey = (string) ($extra['cart_key'] ?? '');

                return str_starts_with($cartKey, "extra-combo-{$comboId}-");
            })->all();

            $body[] = $this->mapComboBodyLine($comboLine, $comboExtras, $context);
        }

        foreach ($extras as $extraLine) {
            $parent = $extraLine['parent_combo_id'] ?? null;
            $cartKey = (string) ($extraLine['cart_key'] ?? '');
            $attachedToCombo = $parent !== null || preg_match('/^extra-combo-\d+-/', $cartKey);
            if ($attachedToCombo) {
                continue;
            }

            $xetuxProductId = (int) ($extraLine['xetux_product_id'] ?? 0);
            if ($xetuxProductId <= 0) {
                throw new RuntimeException(
                    'El extra «'.($extraLine['name'] ?? '').'» no tiene producto Xetux vinculado.'
                );
            }

            $body[] = $this->nestedCatalogProduct(
                $this->resolveCatalogProduct($xetuxProductId, (string) ($extraLine['name'] ?? 'Extra'), $context),
                (int) ($extraLine['quantity'] ?? 1),
                round((float) ($extraLine['unit_price'] ?? 0), 2)
            );
        }

        foreach ($products as $productLine) {
            $xetuxProductId = (int) ($productLine['xetux_product_id'] ?? $productLine['product_id'] ?? 0);
            if ($xetuxProductId <= 0) {
                continue;
            }
            $body[] = $this->nestedCatalogProduct(
                $this->resolveCatalogProduct($xetuxProductId, (string) ($productLine['name'] ?? 'Producto'), $context),
                (int) ($productLine['quantity'] ?? 1),
                round((float) ($productLine['unit_price'] ?? 0), 2)
            );
        }

        if ($body === []) {
            throw new RuntimeException('No hay líneas válidas con ID Xetux para enviar el pedido.');
        }

        return $body;
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapAdditionalLine(array $extraLine): array
    {
        $xetuxId = (int) ($extraLine['xetux_product_id'] ?? $extraLine['xetux_item_id'] ?? 0);

        return [
            'id' => $xetuxId,
            'name' => (string) ($extraLine['name'] ?? 'Extra'),
            'quantity' => (float) ($extraLine['quantity'] ?? 1),
            'price' => round((float) ($extraLine['unit_price'] ?? 0), 2),
            'taxValue' => 0.0,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $combinaciones
     */
    protected function combinacionesToNotes(array $combinaciones): string
    {
        $parts = [];
        foreach ($combinaciones as $index => $row) {
            if (! is_array($row)) {
                continue;
            }
            $kind = (string) ($row['kind'] ?? '');
            if ($kind === 'sauce') {
                $name = trim((string) ($row['proteina'] ?? $row['product_name'] ?? ''));
                $qty = max(1, (int) ($row['quantity'] ?? 1));
                if ($name !== '') {
                    $parts[] = "Salsa: {$name} x{$qty}";
                }
                continue;
            }
            if ($kind === 'drink') {
                $name = trim((string) ($row['proteina'] ?? $row['product_name'] ?? ''));
                if ($name !== '') {
                    $parts[] = "Bebida: {$name}";
                }
                continue;
            }

            $t = $row['textura'] ?? '';
            $p = $row['proteina'] ?? '';
            $c = $row['complemento'] ?? '';
            if ($t || $p || $c) {
                $parts[] = 'Comb '.($index + 1).": {$t} / {$p} / {$c}";
            }
        }

        return implode(' | ', $parts);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function mapPreferenceAdditionals(
        array $comboLine,
        int $xetuxProductId,
        Collection $ingredientsByProduct
    ): array {
        $additionals = [];
        $ingredients = $ingredientsByProduct->get($xetuxProductId, collect());

        if (
            array_key_exists('rollsConSesamo', $comboLine)
            && $comboLine['rollsConSesamo'] === false
        ) {
            $ing = $this->findIngredient($ingredients, ['SESAMO']);
            if ($ing) {
                $additionals[] = $this->ingredientToAdditional($ing, 0);
            }
        }

        if (
            array_key_exists('rollsConQuesoCremaCebollin', $comboLine)
            && $comboLine['rollsConQuesoCremaCebollin'] === false
        ) {
            $ing = $this->findIngredient($ingredients, ['QUESO CREMA', 'CEBOLLIN']);
            if ($ing) {
                $additionals[] = $this->ingredientToAdditional($ing, 0);
            }
        }

        return $additionals;
    }

    protected function findIngredient(Collection $ingredients, array $needles): ?array
    {
        foreach ($ingredients as $ing) {
            $name = strtoupper((string) ($ing['ingredientName'] ?? ''));
            foreach ($needles as $needle) {
                if (str_contains($name, strtoupper($needle))) {
                    return $ing;
                }
            }
        }

        return null;
    }

    /**
     * Salsas y bebida incluidas del combo, como adicionales de precio 0.
     *
     * @param  array<string, mixed>  $comboLine
     * @return array<int, array<string, mixed>>
     */
    protected function mapIncludedSelectionAdditionals(array $comboLine): array
    {
        $additionals = [];

        foreach ($comboLine['included_sauces'] ?? [] as $sauce) {
            if (! is_array($sauce)) {
                continue;
            }
            $mapped = $this->mapIncludedSelection($sauce, 'Salsa');
            if ($mapped !== null) {
                $additionals[] = $mapped;
            }
        }

        $drink = $comboLine['included_drink'] ?? null;
        if (is_array($drink)) {
            $mapped = $this->mapIncludedSelection($drink, 'Bebida');
            if ($mapped !== null) {
                $additionals[] = $mapped;
            }
        }

        return $additionals;
    }

    /**
     * @param  array<string, mixed>  $selection
     * @return array<string, mixed>|null
     */
    protected function mapIncludedSelection(array $selection, string $fallbackName): ?array
    {
        $qty = (int) ($selection['quantity'] ?? 1);
        if ($qty <= 0) {
            return null;
        }

        $xetuxId = (int) ($selection['xetux_product_id'] ?? $selection['xetux_item_id'] ?? 0);
        if ($xetuxId <= 0) {
            return null;
        }

        $name = trim((string) ($selection['product_name'] ?? $selection['name'] ?? ''));

        return [
            'id' => $xetuxId,
            'name' => $name !== '' ? $name : $fallbackName,
            'quantity' => (float) $qty,
            'price' => 0.0,
            'taxValue' => 0.0,
        ];
    }

    /**
     * Deja salsas y bebida dentro de combinaciones para que el panel admin las muestre.
     *
     * @param  array<int, mixed>  $combinaciones
     * @param  array<int, mixed>  $sauces
     * @param  array<string, mixed>|null  $drink
     * @return array<int, array<string, mixed>>
     */
    public function mergeIncludedSelections(array $combinaciones, array $sauces, ?array $drink): array
    {
        $rows = [];
        foreach ($combinaciones as $row) {
            if (! is_array($row)) {
                continue;
            }
            $kind = (string) ($row['kind'] ?? '');
            if ($kind === 'sauce' || $kind === 'drink') {
                continue;
            }
            $rows[] = $row;
        }

        foreach ($sauces as $sauce) {
            if (! is_array($sauce)) {
                continue;
            }
            $qty = (int) ($sauce['quantity'] ?? 0);
            $name = trim((string) ($sauce['product_name'] ?? $sauce['name'] ?? ''));
            if ($qty <= 0 || $name === '') {
                continue;
            }
            $rows[] = [
                'textura' => 'Salsa',
                'proteina' => $name,
                'complemento' => 'x'.$qty,
                'kind' => 'sauce',
                'quantity' => $qty,
                'xetux_product_id' => (int) ($sauce['xetux_product_id'] ?? 0) ?: null,
                'xetux_item_id' => (int) ($sauce['xetux_item_id'] ?? 0) ?: null,
            ];
        }

        if (is_array($drink)) {
            $name = trim((string) ($drink['product_name'] ?? $drink['name'] ?? ''));
            $qty = (int) ($drink['quantity'] ?? 1);
            if ($name !== '' && $qty > 0) {
                $rows[] = [
                    'textura' => 'Bebida',
                    'proteina' => $name,
                    'complemento' => 'Incluida',
                    'kind' => 'drink',
                    'quantity' => $qty,
                    'xetux_product_id' => (int) ($drink['xetux_product_id'] ?? 0) ?: null,
                    'xetux_item_id' => (int) ($drink['xetux_item_id'] ?? 0) ?: null,
                ];
            }
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $ingredient
     * @return array<string, mixed>
     */
    protected function ingredientToAdditional(array $ingredient, float $price): array
    {
        return [
            'id' => (int) $ingredient['ingredientId'],
            'name' => (string) $ingredient['ingredientName'],
            'quantity' => 1.0,
            'price' => $price,
            'taxValue' => 0.0,
        ];
    }

    protected function ingredientsIndexByProductId(): Collection
    {
        try {
            $catalogue = $this->catalogue->fetchCatalogue();
        } catch (RuntimeException) {
            return collect();
        }

        return collect($catalogue['removibleIngredientList'] ?? [])
            ->mapWithKeys(function ($row) {
                $productId = (int) ($row['productId'] ?? 0);

                return [$productId => collect($row['ingredientList'] ?? [])];
            });
    }

    /**
     * Combo Xetux: el promo envuelve productos. Cada combinación de 10 rolls
     * es un producto con adicionales de textura, proteína y complemento.
     * Salsas y bebida van como productos hermanos, no como adicionales.
     *
     * @param  array<string, mixed>  $comboLine
     * @param  array<int, array<string, mixed>>  $comboExtras
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function mapComboBodyLine(array $comboLine, array $comboExtras, array $context): array
    {
        $xetuxProductId = (int) ($comboLine['xetux_product_id'] ?? 0);
        $comboProduct = $this->resolveCatalogProduct(
            $xetuxProductId,
            (string) ($comboLine['name'] ?? 'Combo'),
            $context
        );
        $comboProduct['combo'] = true;

        $groups = $context['promotions'][$xetuxProductId] ?? [
            'rolls' => [],
            'sauces' => [],
            'drinks' => [],
            'toppings' => [],
        ];

        $nested = [];
        foreach ($comboLine['combinaciones'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $kind = (string) ($row['kind'] ?? '');
            if ($kind === 'sauce' || $kind === 'drink') {
                continue;
            }
            if ($this->normalizeLabel((string) ($row['textura'] ?? '')) === 'TOPPING') {
                $topping = $this->matchNamedProduct($groups['toppings'], (string) ($row['proteina'] ?? ''));
                if ($topping) {
                    $nested[] = $this->nestedCatalogProduct($topping, 1, 0);
                }
                continue;
            }

            $roll = $this->selectRollProduct($groups['rolls'], $row);
            if (! $roll) {
                continue;
            }
            $line = $this->nestedCatalogProduct($roll, (int) ($comboLine['quantity'] ?? 1), 0);
            $additionals = [];
            foreach (['textura', 'proteina', 'complemento'] as $field) {
                $additional = $this->modifierAdditional((string) ($row[$field] ?? ''), $context);
                if ($additional) {
                    $additionals[] = $additional;
                }
            }
            if ($additionals !== []) {
                $line['additionals'] = $additionals;
            }
            $nested[] = $line;
        }

        foreach ($comboLine['included_sauces'] ?? [] as $sauce) {
            if (! is_array($sauce)) {
                continue;
            }
            $qty = (int) ($sauce['quantity'] ?? 0);
            if ($qty <= 0) {
                continue;
            }
            $product = $this->matchNamedProduct($groups['sauces'], (string) ($sauce['product_name'] ?? $sauce['name'] ?? ''))
                ?? $this->resolveCatalogProduct((int) ($sauce['xetux_product_id'] ?? 0), (string) ($sauce['product_name'] ?? 'Salsa'), $context);
            if ((int) ($product['id'] ?? 0) <= 0) {
                continue;
            }
            $nested[] = $this->nestedCatalogProduct($product, $qty, 0);
        }

        $drink = $comboLine['included_drink'] ?? null;
        if (is_array($drink) && (int) ($drink['quantity'] ?? 1) > 0) {
            $product = $this->matchNamedProduct($groups['drinks'], (string) ($drink['product_name'] ?? $drink['name'] ?? ''))
                ?? $this->resolveCatalogProduct((int) ($drink['xetux_product_id'] ?? 0), (string) ($drink['product_name'] ?? 'Bebida'), $context);
            if ((int) ($product['id'] ?? 0) > 0) {
                $nested[] = $this->nestedCatalogProduct($product, 1, 0);
            }
        }

        foreach ($comboExtras as $extraLine) {
            $product = $this->resolveCatalogProduct(
                (int) ($extraLine['xetux_product_id'] ?? 0),
                (string) ($extraLine['name'] ?? 'Extra'),
                $context
            );
            $nested[] = $this->nestedCatalogProduct(
                $product,
                (int) ($extraLine['quantity'] ?? 1),
                round((float) ($extraLine['unit_price'] ?? 0), 2)
            );
        }

        return [
            'id' => (int) $comboProduct['id'],
            'product' => [
                'id' => (int) $comboProduct['id'],
                'name' => (string) $comboProduct['name'],
                'code' => (string) ($comboProduct['sku'] ?? ''),
                'promo' => true,
            ],
            'notes' => $this->comboLineNotes($comboLine),
            'quantity' => (int) ($comboLine['quantity'] ?? 1),
            'price' => round((float) ($comboLine['unit_price'] ?? 0), 2),
            'products' => $nested,
        ];
    }

    /**
     * @param  array<string, mixed>  $comboLine
     */
    protected function comboLineNotes(array $comboLine): string
    {
        $notes = $this->combinacionesToNotes($comboLine['combinaciones'] ?? []);
        $extra = [];
        if (array_key_exists('rollsConSesamo', $comboLine) && $comboLine['rollsConSesamo'] !== null) {
            $extra[] = $comboLine['rollsConSesamo']
                ? 'Confirmo que deseo los rolls con sésamo.'
                : 'Sin sésamo.';
        }
        if (array_key_exists('rollsConQuesoCremaCebollin', $comboLine) && $comboLine['rollsConQuesoCremaCebollin'] !== null) {
            $extra[] = $comboLine['rollsConQuesoCremaCebollin']
                ? 'Confirmo que deseo los rolls con queso crema y cebollín.'
                : 'Sin queso crema y cebollín.';
        }
        if ($extra === []) {
            return $notes;
        }

        return trim($notes.' | '.implode(' | ', $extra), ' |');
    }

    /**
     * @param  array<int, array<string, mixed>>  $rollProducts
     * @param  array<string, mixed>  $combination
     * @return array<string, mixed>|null
     */
    protected function selectRollProduct(array $rollProducts, array $combination): ?array
    {
        $protein = $this->normalizeLabel((string) ($combination['proteina'] ?? ''));
        if (str_contains($protein, 'MIXTO')) {
            $mixtos = $this->matchNamedProduct($rollProducts, 'MIXTOS');
            if ($mixtos) {
                return $mixtos;
            }
        }

        $especiales = $this->matchNamedProduct($rollProducts, 'ESPECIALES');
        if ($especiales) {
            return $especiales;
        }

        return $rollProducts[0] ?? null;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>|null
     */
    protected function modifierAdditional(string $name, array $context): ?array
    {
        $key = $this->normalizeLabel($name);
        if ($key === '') {
            return null;
        }
        $additional = $context['additionalsByName'][$key] ?? null;
        if (! is_array($additional)) {
            return null;
        }
        $category = $context['categoryByAdditionalId'][(string) ($additional['id'] ?? '')] ?? null;

        return [
            'id' => (int) ($additional['id'] ?? 0),
            'name' => (string) ($additional['name'] ?? $name),
            'code' => (string) ($additional['sku'] ?? ''),
            'quantity' => 1,
            'price' => 0,
            'taxValue' => 0,
            'categoryId' => (int) ($category['id'] ?? 0),
            'categoryName' => (string) ($category['name'] ?? ''),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $products
     * @return array<string, mixed>|null
     */
    protected function matchNamedProduct(array $products, string $name): ?array
    {
        $needle = $this->normalizeLabel($name);
        if ($needle === '') {
            return null;
        }
        $best = null;
        $bestScore = 0;
        foreach ($products as $product) {
            $label = $this->normalizeLabel((string) ($product['name'] ?? ''));
            if ($label === '') {
                continue;
            }
            $score = 0;
            if ($label === $needle) {
                $score = 100;
            } elseif (str_contains($label, $needle) || str_contains($needle, $label)) {
                $score = 50 + min(strlen($label), strlen($needle));
            }
            if ($score > $bestScore) {
                $best = $product;
                $bestScore = $score;
            }
        }

        return $best;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    protected function resolveCatalogProduct(int $id, string $fallbackName, array $context): array
    {
        $found = $context['productsById'][$id] ?? null;
        if (is_array($found)) {
            return $found;
        }

        return [
            'id' => $id,
            'name' => $fallbackName !== '' ? $fallbackName : 'Producto',
            'sku' => '',
            'combo' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $product
     * @return array<string, mixed>
     */
    protected function nestedCatalogProduct(array $product, int $quantity, float $price): array
    {
        $id = (int) ($product['id'] ?? 0);

        return [
            'id' => $id,
            'product' => [
                'id' => $id,
                'name' => (string) ($product['name'] ?? 'Producto'),
                'code' => (string) ($product['sku'] ?? ''),
                'promo' => (bool) ($product['combo'] ?? false),
            ],
            'notes' => '',
            'quantity' => max(1, $quantity),
            'price' => $price,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function promotionContext(): array
    {
        $empty = [
            'productsById' => [],
            'additionalsByName' => [],
            'categoryByAdditionalId' => [],
            'promotions' => [],
        ];

        try {
            $catalogue = $this->catalogue->fetchCatalogue();
        } catch (RuntimeException) {
            return $empty;
        }

        $data = $catalogue['data'] ?? null;
        if (! is_array($data) || ! isset($data['products'])) {
            $data = $catalogue;
        }
        if (! is_array($data)) {
            return $empty;
        }

        $productsById = [];
        foreach ($data['products'] ?? [] as $product) {
            if (! is_array($product)) {
                continue;
            }
            $id = (int) ($product['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $productsById[$id] = [
                'id' => $id,
                'name' => (string) ($product['name'] ?? ''),
                'sku' => (string) ($product['sku'] ?? ''),
                'combo' => filter_var($product['combo'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ];
        }

        $categoriesById = [];
        foreach ($data['categories'] ?? [] as $category) {
            if (! is_array($category)) {
                continue;
            }
            $categoriesById[(string) ($category['id'] ?? '')] = [
                'id' => (int) ($category['id'] ?? 0),
                'name' => (string) ($category['name'] ?? ''),
            ];
        }

        $additionalsByName = [];
        foreach ($data['additionals'] ?? [] as $additional) {
            if (! is_array($additional)) {
                continue;
            }
            $key = $this->normalizeLabel((string) ($additional['name'] ?? ''));
            if ($key !== '') {
                $additionalsByName[$key] = $additional;
            }
        }

        $categoryByAdditionalId = [];
        foreach ($data['additionalCategories'] ?? [] as $link) {
            if (! is_array($link)) {
                continue;
            }
            $optionId = (string) ($link['optionId'] ?? '');
            $category = $categoriesById[(string) ($link['groupId'] ?? '')] ?? null;
            if ($optionId !== '' && is_array($category)) {
                $categoryByAdditionalId[$optionId] = $category;
            }
        }

        $familyNames = [];
        foreach ($data['familyPromotions'] ?? [] as $family) {
            if (! is_array($family)) {
                continue;
            }
            $familyNames[(string) ($family['parentProductId'] ?? '').':'.(string) ($family['familyPromotionId'] ?? '')] = (string) ($family['name'] ?? '');
        }

        $promotions = [];
        foreach ($data['productPromotions'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $parentId = (int) ($row['parentProductId'] ?? 0);
            $product = $productsById[(int) ($row['productId'] ?? 0)] ?? null;
            if ($parentId <= 0 || ! is_array($product)) {
                continue;
            }
            $familyName = $this->normalizeLabel($familyNames[$parentId.':'.(string) ($row['familyPromotionId'] ?? '')] ?? '');
            $bucket = 'drinks';
            if (str_contains($familyName, 'ROLL')) {
                $bucket = 'rolls';
            } elseif (str_contains($familyName, 'SALSA')) {
                $bucket = 'sauces';
            } elseif (str_contains($familyName, 'TOPPING') || str_contains($familyName, 'RACION')) {
                $bucket = 'toppings';
            }
            $promotions[$parentId][$bucket][] = $product;
        }

        return [
            'productsById' => $productsById,
            'additionalsByName' => $additionalsByName,
            'categoryByAdditionalId' => $categoryByAdditionalId,
            'promotions' => $promotions,
        ];
    }

    protected function normalizeLabel(string $value): string
    {
        $value = strtoupper(trim($value));
        $value = strtr($value, [
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ñ' => 'N', 'Ü' => 'U',
            'á' => 'A', 'é' => 'E', 'í' => 'I', 'ó' => 'O', 'ú' => 'U', 'ñ' => 'N', 'ü' => 'U',
        ]);

        return trim((string) preg_replace('/[^A-Z0-9]+/', ' ', $value));
    }

    protected function generateXetuxOrderId(int $localOrderId): int
    {
        // Xetux guarda orders[].id como int de 32 bits: debe ser menor a 2147483648.
        // Un dígito de año + MMDD + 4 dígitos de la orden local cabe siempre en ese rango.
        $max = 2147483647;
        $sequence = abs($localOrderId) % 10000;
        $yearDigit = ((int) now()->format('y')) % 10;
        $id = (int) sprintf(
            '%d%02d%02d%04d',
            $yearDigit,
            (int) now()->format('n'),
            (int) now()->format('j'),
            $sequence
        );

        if ($id < 1 || $id > $max) {
            $id = (abs($localOrderId) % $max) ?: 1;
        }

        return $id;
    }

    protected function generateTrackingCode(): string
    {
        return 'A'.strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));
    }

    /**
     * Resuelve líneas del carrito con IDs Xetux desde la BD.
     *
     * @param  array<int, array<string, mixed>>  $cartLines
     * @return array<int, array<string, mixed>>
     */
    public function resolveCartLines(array $cartLines): array
    {
        return collect($cartLines)->map(function ($line) {
            $type = $line['type'] ?? '';
            if ($type === 'combo' && ! empty($line['combo_id'])) {
                $combo = Combo::find((int) $line['combo_id']);
                if ($combo) {
                    $line['xetux_product_id'] = $combo->xetux_product_id;
                    $line['xetux_item_id'] = $combo->xetux_item_id;
                    if (is_string($combo->name) && $combo->name !== '') {
                        $line['name'] = $combo->name;
                    }
                }
                $line['combinaciones'] = $this->mergeIncludedSelections(
                    is_array($line['combinaciones'] ?? null) ? $line['combinaciones'] : [],
                    is_array($line['included_sauces'] ?? null) ? $line['included_sauces'] : [],
                    is_array($line['included_drink'] ?? null) ? $line['included_drink'] : null
                );
            }
            if ($type === 'extra' && ! empty($line['extra_id'])) {
                $extra = Extra::find((int) $line['extra_id']);
                if ($extra) {
                    $line['xetux_product_id'] = $extra->xetux_product_id;
                    $line['xetux_item_id'] = $extra->xetux_item_id;
                }
            }

            return $line;
        })->all();
    }

    /**
     * Arma el mismo JSON de Xetux a partir de la orden ya guardada.
     *
     * @return array<string, mixed>
     */
    public function payloadForStoredOrder(Order $order): array
    {
        $order->loadMissing(['customer', 'orderItems.combo', 'orderItems.extra', 'orderItems.product']);

        $customer = $order->customer;
        if (! $customer) {
            $customer = new Customer([
                'name' => 'Cliente',
                'email' => '',
            ]);
            $customer->customer_id = 0;
        }

        $payload = $this->buildPayload(
            $order,
            $customer,
            $this->resolveCartLines($this->cartLinesFromOrderItems($order)),
            [
                'notes' => (string) ($order->notes ?? ''),
                'delivery_type' => (string) ($order->delivery_type ?? 'pickup'),
            ]
        );

        if ($order->xetux_order_id) {
            $payload['orders'][0]['id'] = (int) $order->xetux_order_id;
        }
        if (is_string($order->xetux_tracking_number) && $order->xetux_tracking_number !== '') {
            $payload['orders'][0]['trackingNumber'] = $order->xetux_tracking_number;
            $payload['orders'][0]['trackingShort'] = $order->xetux_tracking_number;
        }

        return $payload;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function cartLinesFromOrderItems(Order $order): array
    {
        $lines = [];

        foreach ($order->orderItems as $item) {
            if ($item->combo_id) {
                [$combinaciones, $sauces, $drink] = $this->splitStoredCombinaciones($item->combinaciones);
                $combo = $item->combo;
                $lines[] = [
                    'type' => 'combo',
                    'combo_id' => (int) $item->combo_id,
                    'name' => $combo?->name,
                    'quantity' => (int) $item->quantity,
                    'unit_price' => (float) ($combo?->price_eur ?? 0),
                    'combinaciones' => $combinaciones,
                    'included_sauces' => $sauces,
                    'included_drink' => $drink,
                ];
                continue;
            }

            if ($item->extra_id) {
                $extra = $item->extra;
                $lines[] = [
                    'type' => 'extra',
                    'extra_id' => (int) $item->extra_id,
                    'name' => $extra?->title ?? 'Extra',
                    'quantity' => (int) $item->quantity,
                    'unit_price' => (float) ($extra?->price_eur ?? 0),
                    'xetux_product_id' => $extra?->xetux_product_id,
                    'xetux_item_id' => $extra?->xetux_item_id,
                ];
                continue;
            }

            if ($item->product_id) {
                $product = $item->product;
                $lines[] = [
                    'type' => 'product',
                    'product_id' => (int) $item->product_id,
                    'name' => $product?->name ?? 'Producto',
                    'quantity' => (int) $item->quantity,
                    'unit_price' => (float) ($product?->price_eur ?? 0),
                ];
            }
        }

        return $lines;
    }

    /**
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, mixed>>, 2: array<string, mixed>|null}
     */
    protected function splitStoredCombinaciones(mixed $stored): array
    {
        $rows = [];
        if (is_array($stored) && $stored !== []) {
            $isRow = isset($stored['textura']) || isset($stored['proteina']) || isset($stored['kind']);
            $rows = $isRow ? [$stored] : array_values($stored);
        }

        $combinaciones = [];
        $sauces = [];
        $drink = null;

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $kind = (string) ($row['kind'] ?? '');
            if ($kind === 'sauce') {
                $sauces[] = [
                    'product_name' => (string) ($row['proteina'] ?? $row['product_name'] ?? ''),
                    'quantity' => (int) ($row['quantity'] ?? 1),
                    'xetux_product_id' => $row['xetux_product_id'] ?? null,
                    'xetux_item_id' => $row['xetux_item_id'] ?? null,
                ];
                continue;
            }
            if ($kind === 'drink' && $drink === null) {
                $drink = [
                    'product_name' => (string) ($row['proteina'] ?? $row['product_name'] ?? ''),
                    'quantity' => 1,
                    'xetux_product_id' => $row['xetux_product_id'] ?? null,
                    'xetux_item_id' => $row['xetux_item_id'] ?? null,
                ];
                continue;
            }
            $combinaciones[] = $row;
        }

        return [$combinaciones, $sauces, $drink];
    }
}
