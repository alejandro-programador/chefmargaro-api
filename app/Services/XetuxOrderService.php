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

        $ingredientsByProduct = $this->ingredientsIndexByProductId();

        return [
            'keyXpedidos' => config('xetux.key_xpedidos'),
            'orders' => [
                [
                    'id' => $xetuxOrderId,
                    'systemTypeId' => (int) config('xetux.system_type_id', 1),
                    'trackingNumber' => $tracking,
                    'trackingShort' => $tracking,
                    'notes' => $notes !== '' ? $notes : 'Pedido ecommerce Chef Margaro',
                    'payformId' => (int) config('xetux.payform_id', 1),
                    'createdAt' => $createdAt,
                    'client' => [
                        'id' => (int) ($customer->customer_id + 3000000),
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
                    'totalDiscount' => 0.0,
                    'tip' => 0.0,
                    'shippingCost' => 0.0,
                    'body' => $this->buildBodyLines($cartLines, $ingredientsByProduct),
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
     * @return array<int, array<string, mixed>>
     */
    protected function buildBodyLines(array $cartLines, Collection $ingredientsByProduct): array
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

            $lineNotes = $this->combinacionesToNotes($comboLine['combinaciones'] ?? []);
            $additionals = [];

            $comboExtras = $extras->filter(function ($extra) use ($comboId) {
                $parent = $extra['parent_combo_id'] ?? null;
                if ($parent !== null && (int) $parent === $comboId) {
                    return true;
                }
                $cartKey = (string) ($extra['cart_key'] ?? '');
                return str_starts_with($cartKey, "extra-combo-{$comboId}-");
            });

            foreach ($comboExtras as $extraLine) {
                $additionals[] = $this->mapAdditionalLine($extraLine);
            }

            $additionals = array_merge(
                $additionals,
                $this->mapPreferenceAdditionals($comboLine, $xetuxProductId, $ingredientsByProduct)
            );

            $unitPrice = (float) ($comboLine['unit_price'] ?? 0);
            $qty = (float) ($comboLine['quantity'] ?? 1);

            $body[] = [
                'id' => $xetuxProductId,
                'product' => [
                    'id' => $xetuxProductId,
                    'name' => (string) ($comboLine['name'] ?? 'Combo'),
                ],
                'notes' => $lineNotes,
                'additionals' => $additionals,
                'quantity' => $qty,
                'price' => round($unitPrice, 2),
            ];
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

            $body[] = [
                'id' => $xetuxProductId,
                'product' => [
                    'id' => $xetuxProductId,
                    'name' => (string) ($extraLine['name'] ?? 'Extra'),
                ],
                'notes' => '',
                'additionals' => [],
                'quantity' => (float) ($extraLine['quantity'] ?? 1),
                'price' => round((float) ($extraLine['unit_price'] ?? 0), 2),
            ];
        }

        foreach ($products as $productLine) {
            $xetuxProductId = (int) ($productLine['xetux_product_id'] ?? $productLine['product_id'] ?? 0);
            if ($xetuxProductId <= 0) {
                continue;
            }
            $body[] = [
                'id' => $xetuxProductId,
                'product' => [
                    'id' => $xetuxProductId,
                    'name' => (string) ($productLine['name'] ?? 'Producto'),
                ],
                'notes' => '',
                'additionals' => [],
                'quantity' => (float) ($productLine['quantity'] ?? 1),
                'price' => round((float) ($productLine['unit_price'] ?? 0), 2),
            ];
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

    protected function generateXetuxOrderId(int $localOrderId): int
    {
        $base = (int) (now()->format('ymd').str_pad((string) ($localOrderId % 10000), 4, '0', STR_PAD_LEFT));
        $suffix = random_int(10, 99);

        return (int) ($base * 100 + $suffix);
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
                }
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
}
