<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StorePaymentRequest;
use App\Http\Resources\Api\V1\PaymentResource;
use App\Models\Order;
use App\Models\Payment;
use App\Support\BranchScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PaymentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Payment::with(['order', 'verifications']);

        $branchId = BranchScope::requestedBranchId($request);
        if ($branchId !== null) {
            $query->whereHas('order.customer', fn ($q) => $q->where('branch_id', $branchId));
        }

        // Filter by order_id
        if ($request->has('order_id')) {
            $query->where('order_id', $request->get('order_id'));
        }

        // Filter by payment_status
        if ($request->has('payment_status')) {
            $query->where('payment_status', $request->get('payment_status'));
        }

        // Filter by payment_method
        if ($request->has('payment_method')) {
            $query->where('payment_method', $request->get('payment_method'));
        }

        $perPage = $request->get('per_page', 15);
        $payments = $query->paginate($perPage);

        return PaymentResource::collection($payments);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePaymentRequest $request)
    {
        $data = $request->validated();
        $order = Order::with('customer')->find($data['order_id']);
        if ($order) {
            $data['branch_id'] = $order->branch_id ?? $order->customer->branch_id ?? null;
        }

        // Handle image upload
        if ($request->hasFile('proof_image')) {
            $image = $request->file('proof_image');
            $imagePath = $image->store('payments', 'public');
            $data['proof_image_url'] = Storage::url($imagePath);
        }

        // Handle reference number if provided
        if ($request->has('reference_number')) {
            $data['reference_number'] = $request->input('reference_number');
        }

        // Remove proof_image from data array if it exists (it's not a database column)
        unset($data['proof_image']);

        $payment = Payment::create($data);
        $payment->load(['order', 'verifications']);

        return response()->json([
            'message' => 'Payment created successfully',
            'data' => new PaymentResource($payment),
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Payment $payment)
    {
        $branchId = BranchScope::requestedBranchId($request);
        if ($branchId !== null && (int) ($payment->branch_id ?? 0) !== $branchId) {
            abort(404);
        }
        $payment->load(['order', 'verifications']);
        return new PaymentResource($payment);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Payment $payment)
    {
        $branchId = BranchScope::requestedBranchId($request);
        if ($branchId !== null && (int) ($payment->branch_id ?? 0) !== $branchId) {
            abort(404);
        }
        $request->validate([
            'order_id' => ['sometimes', 'integer', 'exists:orders,order_id'],
            'payment_method' => ['sometimes', 'string', 'max:50'],
            'payment_status' => ['sometimes', 'string', 'in:pending,completed,failed,refunded'],
            'payment_date' => ['nullable', 'date'],
            'proof_image_url' => ['nullable', 'string', 'max:255', 'url'],
        ]);

        $payment->update($request->only([
            'order_id',
            'payment_method',
            'payment_status',
            'payment_date',
            'proof_image_url',
        ]));

        $payment->load(['order', 'verifications']);

        return response()->json([
            'message' => 'Payment updated successfully',
            'data' => new PaymentResource($payment),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Payment $payment)
    {
        $branchId = BranchScope::requestedBranchId($request);
        if ($branchId !== null && (int) ($payment->branch_id ?? 0) !== $branchId) {
            abort(404);
        }
        $payment->delete();

        return response()->noContent();
    }
}
