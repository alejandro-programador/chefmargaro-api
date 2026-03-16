<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreOrderRequest;
use App\Http\Resources\Api\V1\OrderResource;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Support\BranchScope;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Order::with(['customer', 'orderItems.product', 'orderItems.combo', 'payments']);

        $branchId = BranchScope::requestedBranchId($request);
        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        // Filter by customer_id
        if ($request->has('customer_id')) {
            $query->where('customer_id', $request->get('customer_id'));
        }

        // Filter by payment_status
        if ($request->has('payment_status')) {
            $query->where('payment_status', $request->get('payment_status'));
        }

        // Filter by date range
        if ($request->has('date_from')) {
            $query->whereDate('order_date', '>=', $request->get('date_from'));
        }

        if ($request->has('date_to')) {
            $query->whereDate('order_date', '<=', $request->get('date_to'));
        }

        $perPage = $request->get('per_page', 15);
        $orders = $query->paginate($perPage);

        return OrderResource::collection($orders);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreOrderRequest $request)
    {
        $data = $request->validated();
        $orderItems = $data['order_items'];
        unset($data['order_items']);

        $customer = Customer::find($data['customer_id']);
        if ($customer && $customer->branch_id !== null) {
            $data['branch_id'] = $customer->branch_id;
        }

        // Generar tracking_token único
        do {
            $data['tracking_token'] = Str::random(32);
        } while (Order::where('tracking_token', $data['tracking_token'])->exists());

        $order = Order::create($data);

        // Create order items
        foreach ($orderItems as $item) {
            OrderItem::create([
                'order_id' => $order->order_id,
                'product_id' => $item['product_id'] ?? null,
                'combo_id' => $item['combo_id'] ?? null,
                'quantity' => $item['quantity'],
                'combinaciones' => $item['combinaciones'] ?? null,
            ]);
        }

        $order->load(['customer', 'orderItems.product', 'orderItems.combo', 'payments']);

        return response()->json([
            'message' => 'Order created successfully',
            'data' => new OrderResource($order),
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Order $order)
    {
        $branchId = BranchScope::requestedBranchId($request);
        if ($branchId !== null && (int) ($order->branch_id ?? 0) !== $branchId) {
            abort(404);
        }
        $order->load('customer');
        $order->load(['customer', 'orderItems.product', 'orderItems.combo', 'payments']);
        return new OrderResource($order);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Order $order)
    {
        $branchId = BranchScope::requestedBranchId($request);
        if ($branchId !== null && (int) ($order->branch_id ?? 0) !== $branchId) {
            abort(404);
        }
        $request->validate([
            'customer_id' => ['sometimes', 'integer', 'exists:customers,customer_id'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,branch_id'],
            'order_date' => ['sometimes', 'date'],
            'total_amount' => ['sometimes', 'numeric', 'min:0'],
            'payment_status' => ['sometimes', 'string', 'max:20'],
            'delivery_type' => ['sometimes', 'string', 'max:20'],
            'order_status' => ['sometimes', 'string', 'in:pending_payment,payment_verified,completed,cancelled'],
        ]);

        $data = $request->only([
            'customer_id',
            'branch_id',
            'order_date',
            'total_amount',
            'payment_status',
            'delivery_type',
            'order_status',
        ]);
        if (array_key_exists('customer_id', $data) && $data['customer_id']) {
            $customer = Customer::find($data['customer_id']);
            if ($customer && $customer->branch_id !== null) {
                $data['branch_id'] = $customer->branch_id;
            }
        }
        $order->update($data);

        $order->load(['customer', 'orderItems.product', 'orderItems.combo', 'payments']);

        return response()->json([
            'message' => 'Order updated successfully',
            'data' => new OrderResource($order),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Order $order)
    {
        $branchId = BranchScope::requestedBranchId($request);
        if ($branchId !== null && (int) ($order->branch_id ?? 0) !== $branchId) {
            abort(404);
        }
        $order->delete();

        return response()->noContent();
    }

    /**
     * Generate tracking link for an order (for WhatsApp bot)
     * POST /api/v1/orders/{order}/generate-tracking-link
     */
    public function generateTrackingLink(Request $request, Order $order)
    {
        $branchId = BranchScope::requestedBranchId($request);
        if ($branchId !== null && (int) ($order->branch_id ?? 0) !== $branchId) {
            abort(404);
        }

        // Si ya tiene tracking_token, generar el link
        if ($order->tracking_token) {
            $frontendUrl = config('app.frontend_url', 'http://localhost:3000');
            $trackingLink = rtrim($frontendUrl, '/') . '/order-tracking/' . $order->tracking_token;
            return response()->json([
                'message' => 'Tracking link generated successfully',
                'tracking_token' => $order->tracking_token,
                'tracking_link' => $trackingLink,
            ]);
        }

        // Generar nuevo tracking_token si no existe
        do {
            $trackingToken = Str::random(32);
        } while (Order::where('tracking_token', $trackingToken)->exists());

        $order->update(['tracking_token' => $trackingToken]);
        $frontendUrl = config('app.frontend_url', 'http://localhost:3000');
        $trackingLink = rtrim($frontendUrl, '/') . '/order-tracking/' . $trackingToken;

        return response()->json([
            'message' => 'Tracking link generated successfully',
            'tracking_token' => $trackingToken,
            'tracking_link' => $trackingLink,
        ]);
    }

    /**
     * Show order details by tracking token (public endpoint)
     * GET /api/v1/orders/tracking/{trackingToken}
     */
    public function showByTrackingToken($trackingToken)
    {
        $order = Order::where('tracking_token', $trackingToken)->first();

        if (!$order) {
            return response()->json([
                'message' => 'Order not found',
            ], 404);
        }

        $order->load(['customer', 'orderItems.product', 'orderItems.combo', 'payments']);

        return new OrderResource($order);
    }
}
