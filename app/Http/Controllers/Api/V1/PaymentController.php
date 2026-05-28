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
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    /**
     * Generates or retrieves a unique payment-status URL by reference number.
     * Public endpoint for bot integrations.
     * GET /api/v1/payments/generate-status-link?reference_number=ABC123&amount=25.50
     */
    public function generateStatusLink(Request $request)
    {
        $validated = $request->validate([
            'reference_number' => ['required', 'string', 'max:50'],
            'amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        $payment = Payment::with('order')
            ->where('reference_number', $validated['reference_number'])
            ->latest('payment_id')
            ->first();

        if (! $payment) {
            $reference = trim($validated['reference_number']);

            $payment = Payment::create([
                'order_id' => null,
                'payment_method' => 'bank_transfer',
                'payment_status' => 'pending',
                'payment_date' => now(),
                'reference_number' => $reference,
                'reported_amount' => $validated['amount'],
            ]);
        } elseif ($payment->order_id === null) {
            $payment->update(['reported_amount' => $validated['amount']]);
        }

        if (! $payment->status_view_token) {
            do {
                $token = Str::random(48);
            } while (Payment::where('status_view_token', $token)->exists());

            $payment->update(['status_view_token' => $token]);
        }

        $payment->refresh();

        $publicBase = config('app.public_url') ?: config('app.url');
        $apiBase = rtrim((string) config('app.api_url'), '/');
        $statusPagePath = '/'.ltrim((string) config('app.payment_status_page_path', '/webapp/payment-status.html'), '/');
        $displayAmount = $payment->order?->total_amount ?? $payment->reported_amount ?? $validated['amount'];
        $statusUrl = rtrim((string) $publicBase, '/').$statusPagePath
            .'?token='.$payment->status_view_token
            .'&api_base='.urlencode($apiBase)
            .'&amount='.urlencode((string) $displayAmount);

        return response()->json([
            'message' => 'URL de status de pago generada correctamente.',
            'reference_number' => $payment->reference_number,
            'payment_id' => $payment->payment_id,
            'status_view_token' => $payment->status_view_token,
            'status_url' => $statusUrl,
            'amount' => (float) $displayAmount,
        ]);
    }

    /**
     * Public endpoint to check payment status by status-view token.
     * GET /api/v1/payments/status-view/{token}
     */
    public function showByStatusViewToken(string $token)
    {
        $payment = Payment::with('order')
            ->where('status_view_token', $token)
            ->first();

        if (! $payment) {
            return response()->json([
                'message' => 'Enlace de estado no válido.',
            ], 404);
        }

        return response()->json([
            'payment_id' => $payment->payment_id,
            'order_id' => $payment->order_id,
            'reference_number' => $payment->reference_number,
            'payment_reference_number' => $payment->payment_reference_number,
            'payment_method' => $payment->payment_method,
            'payment_status' => $payment->payment_status,
            'payment_date' => $payment->payment_date,
            'amount' => $payment->order?->total_amount ?? $payment->reported_amount,
            'reported_amount' => $payment->reported_amount,
            'proof_image_url' => $payment->proof_image_url,
            'requires_payment_submission' => empty($payment->proof_image_url),
            'updated_at' => $payment->updated_at,
        ]);
    }

    /**
     * Public endpoint to upload payment proof by status-view token.
     * POST /api/v1/payments/status-view/{token}/submit-proof
     */
    public function submitProofByStatusViewToken(Request $request, string $token)
    {
        $payment = Payment::where('status_view_token', $token)->first();

        if (! $payment) {
            return response()->json([
                'message' => 'Enlace de pago no válido.',
            ], 404);
        }

        $validated = $request->validate([
            'proof_image' => ['required', 'image', 'max:5120'],
            'payment_method' => ['required', 'string', Rule::in(['cash', 'bank_transfer', 'mobile_payment'])],
            'payment_reference_number' => [
                'nullable',
                'string',
                'regex:/^\d{1,12}$/',
            ],
        ]);

        if ($validated['payment_method'] === 'mobile_payment' && empty($validated['payment_reference_number'])) {
            return response()->json([
                'message' => 'El número de referencia es obligatorio para Pago Móvil.',
                'errors' => [
                    'payment_reference_number' => ['El número de referencia es obligatorio para Pago Móvil.'],
                ],
            ], 422);
        }

        if ($validated['payment_method'] !== 'mobile_payment') {
            $validated['payment_reference_number'] = null;
        }

        $imagePath = $request->file('proof_image')->store('payments', 'public');
        $updates = [
            'proof_image_url' => Storage::url($imagePath),
            'payment_date' => now(),
            'payment_status' => 'pending',
            'payment_method' => $validated['payment_method'],
            'payment_reference_number' => $validated['payment_reference_number'] ?? null,
        ];

        $payment->update($updates);
        $payment->refresh();

        return response()->json([
            'message' => 'Comprobante recibido correctamente.',
            'payment_id' => $payment->payment_id,
            'reference_number' => $payment->reference_number,
            'payment_status' => $payment->payment_status,
            'proof_image_url' => $payment->proof_image_url,
            'requires_payment_submission' => false,
        ]);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Payment::with(['order', 'verifications']);

        $branchId = BranchScope::requestedBranchId($request);
        if ($branchId !== null) {
            $query->where(function ($q) use ($branchId) {
                $q->where('branch_id', $branchId)
                    ->orWhere(function ($sub) {
                        // Pagos creados por referencia (bot) sin orden aún asociada
                        $sub->whereNull('order_id')->whereNotNull('reference_number');
                    })
                    ->orWhereHas('order', function ($orderQ) use ($branchId) {
                        $orderQ->where('branch_id', $branchId)
                            ->orWhereHas('customer', fn ($customerQ) => $customerQ->where('branch_id', $branchId));
                    });
            });
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

        $sortBy = $request->get('sort', 'created_at');
        $sortOrder = strtolower((string) $request->get('order', 'desc')) === 'asc' ? 'asc' : 'desc';
        $allowedSort = ['created_at', 'payment_date', 'payment_id'];
        if (! in_array($sortBy, $allowedSort, true)) {
            $sortBy = 'created_at';
        }
        $query->orderBy($sortBy, $sortOrder);

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
        if ($branchId !== null && ! $this->paymentAccessibleForBranch($payment, $branchId)) {
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
        if ($branchId !== null && ! $this->paymentAccessibleForBranch($payment, $branchId)) {
            abort(404);
        }
        $request->validate([
            'order_id' => ['sometimes', 'integer', 'exists:orders,order_id'],
            'payment_method' => ['sometimes', 'string', 'max:50'],
            'payment_status' => ['sometimes', 'string', 'in:pending,completed,failed,refunded'],
            'payment_date' => ['nullable', 'date'],
            'proof_image_url' => ['nullable', 'string', 'max:255', 'url'],
            'reported_amount' => ['nullable', 'numeric', 'min:0.01'],
        ]);

        $payment->update($request->only([
            'order_id',
            'payment_method',
            'payment_status',
            'payment_date',
            'proof_image_url',
            'reported_amount',
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
        if ($branchId !== null && ! $this->paymentAccessibleForBranch($payment, $branchId)) {
            abort(404);
        }
        $payment->delete();

        return response()->noContent();
    }

    private function paymentAccessibleForBranch(Payment $payment, int $branchId): bool
    {
        if ((int) ($payment->branch_id ?? 0) === $branchId) {
            return true;
        }

        if ($payment->order_id === null && $payment->reference_number) {
            return true;
        }

        $payment->loadMissing('order.customer');

        return (int) ($payment->order?->branch_id ?? 0) === $branchId
            || (int) ($payment->order?->customer?->branch_id ?? 0) === $branchId;
    }
}
