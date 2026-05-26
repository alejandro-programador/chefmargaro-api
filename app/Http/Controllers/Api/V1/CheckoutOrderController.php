<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreCheckoutOrderRequest;
use App\Http\Resources\Api\V1\OrderResource;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\XetuxOrderService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class CheckoutOrderController extends Controller
{
    /**
     * Vista previa del JSON que se enviará a Xetux (sin guardar ni enviar).
     */
    public function previewXetux(StoreCheckoutOrderRequest $request, XetuxOrderService $xetuxOrders)
    {
        try {
            $payload = $this->buildXetuxPayload($request->validated(), $xetuxOrders);

            return response()->json([
                'xetux_send_url' => config('xetux.send_url'),
                'xetux_payload' => $payload,
            ]);
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function store(StoreCheckoutOrderRequest $request, XetuxOrderService $xetuxOrders)
    {
        $data = $request->validated();
        $xetuxPayload = null;

        try {
            $order = DB::transaction(function () use ($data, $xetuxOrders, &$xetuxPayload) {
                $customer = $this->resolveCustomer($data);
                $cartLines = $xetuxOrders->resolveCartLines($data['cart_lines']);

                do {
                    $trackingToken = Str::random(32);
                } while (Order::where('tracking_token', $trackingToken)->exists());

                $order = Order::create([
                    'customer_id' => $customer->customer_id,
                    'branch_id' => $data['branch_id'],
                    'order_date' => now(),
                    'total_amount' => $data['total_amount'],
                    'payment_status' => 'pending',
                    'delivery_type' => $data['delivery_type'],
                    'order_status' => 'pending_payment',
                    'tracking_token' => $trackingToken,
                    'notes' => $data['notes'] ?? null,
                ]);

                foreach ($cartLines as $line) {
                    $this->createOrderItem($order->order_id, $line);
                }

                $xetuxPayload = $xetuxOrders->buildPayload(
                    $order,
                    $customer,
                    $cartLines,
                    $this->checkoutMeta($data)
                );
                $xetuxOrders->send($xetuxPayload);

                $xetuxOrder = $xetuxPayload['orders'][0] ?? [];
                $order->update([
                    'xetux_order_id' => $xetuxOrder['id'] ?? null,
                    'xetux_tracking_number' => $xetuxOrder['trackingNumber'] ?? null,
                ]);

                return $order->fresh();
            });

            $order->load(['customer', 'orderItems.combo', 'orderItems.extra', 'orderItems.product']);

            return response()->json([
                'message' => 'Orden creada y sincronizada con Xetux',
                'data' => new OrderResource($order),
                'xetux_send_url' => config('xetux.send_url'),
                'xetux_payload' => $xetuxPayload,
            ], 201);
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'xetux_send_url' => config('xetux.send_url'),
                'xetux_payload' => $xetuxPayload,
            ], 422);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Error al procesar el pedido: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function buildXetuxPayload(array $data, XetuxOrderService $xetuxOrders): array
    {
        $customer = $this->customerForPreview($data);
        $cartLines = $xetuxOrders->resolveCartLines($data['cart_lines']);

        $previewOrderId = ((int) Order::max('order_id')) + 1;
        $order = new Order([
            'order_id' => $previewOrderId,
            'total_amount' => $data['total_amount'],
            'notes' => $data['notes'] ?? null,
        ]);

        return $xetuxOrders->buildPayload($order, $customer, $cartLines, $this->checkoutMeta($data));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function checkoutMeta(array $data): array
    {
        return [
            'phone' => $data['customer']['phone'] ?? '',
            'notes' => $data['notes'] ?? '',
            'delivery_address' => $data['delivery_address'] ?? '',
            'reference_point' => $data['reference_point'] ?? '',
        ];
    }

    /**
     * Cliente para vista previa (sin crear ni actualizar en BD).
     *
     * @param  array<string, mixed>  $data
     */
    protected function customerForPreview(array $data): Customer
    {
        $email = strtolower(trim($data['customer']['email']));
        $existing = Customer::whereRaw('LOWER(email) = ?', [$email])->first();

        if ($existing) {
            return $existing;
        }

        $customer = new Customer;
        $customer->customer_id = 0;
        $customer->name = $data['customer']['name'];
        $customer->email = $email;

        return $customer;
    }

    protected function resolveCustomer(array $data): Customer
    {
        $email = strtolower(trim($data['customer']['email']));
        $customer = Customer::whereRaw('LOWER(email) = ?', [$email])->first();

        if ($customer) {
            $customer->update([
                'name' => $data['customer']['name'],
                'branch_id' => $data['branch_id'],
            ]);

            return $customer->fresh();
        }

        return Customer::create([
            'email' => $email,
            'name' => $data['customer']['name'],
            'branch_id' => $data['branch_id'],
            'signup_date' => now()->toDateString(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $line
     */
    protected function createOrderItem(int $orderId, array $line): void
    {
        $type = $line['type'] ?? '';
        $item = [
            'order_id' => $orderId,
            'quantity' => (int) ($line['quantity'] ?? 1),
            'product_id' => null,
            'combo_id' => null,
            'extra_id' => null,
            'combinaciones' => null,
        ];

        if ($type === 'combo') {
            $item['combo_id'] = (int) $line['combo_id'];
            if (! empty($line['combinaciones'])) {
                $item['combinaciones'] = $line['combinaciones'];
            }
        } elseif ($type === 'extra') {
            $item['extra_id'] = (int) $line['extra_id'];
        } elseif ($type === 'product') {
            $item['product_id'] = (int) ($line['product_id'] ?? 0) ?: null;
        }

        OrderItem::create($item);
    }
}
