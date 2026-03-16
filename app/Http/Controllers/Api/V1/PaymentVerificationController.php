<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PaymentVerificationResource;
use App\Models\PaymentVerification;
use Illuminate\Http\Request;

class PaymentVerificationController extends Controller
{
    public function index(Request $request)
    {
        $query = PaymentVerification::with(['payment', 'verifier']);

        if ($request->has('payment_id')) {
            $query->where('payment_id', $request->get('payment_id'));
        }

        if ($request->has('verifier_id')) {
            $query->where('verifier_id', $request->get('verifier_id'));
        }

        if ($request->has('verification_status')) {
            $query->where('verification_status', $request->get('verification_status'));
        }

        $perPage = $request->get('per_page', 15);
        return PaymentVerificationResource::collection($query->paginate($perPage));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'payment_id' => ['required', 'integer', 'exists:payments,payment_id'],
            'verifier_id' => ['required', 'integer', 'exists:users,user_id'],
            'verification_status' => ['sometimes', 'string', 'in:pending,approved,rejected'],
            'verification_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $verification = PaymentVerification::create($data);
        $verification->load(['payment', 'verifier']);

        return response()->json([
            'message' => 'Payment verification created successfully',
            'data' => new PaymentVerificationResource($verification),
        ], 201);
    }

    public function show(PaymentVerification $paymentVerification)
    {
        $paymentVerification->load(['payment', 'verifier']);
        return new PaymentVerificationResource($paymentVerification);
    }

    public function update(Request $request, PaymentVerification $paymentVerification)
    {
        $data = $request->validate([
            'payment_id' => ['sometimes', 'integer', 'exists:payments,payment_id'],
            'verifier_id' => ['sometimes', 'integer', 'exists:users,user_id'],
            'verification_status' => ['sometimes', 'string', 'in:pending,approved,rejected'],
            'verification_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $paymentVerification->update($data);
        $paymentVerification->load(['payment', 'verifier']);

        return response()->json([
            'message' => 'Payment verification updated successfully',
            'data' => new PaymentVerificationResource($paymentVerification),
        ]);
    }

    public function destroy(PaymentVerification $paymentVerification)
    {
        $paymentVerification->delete();
        return response()->noContent();
    }
}
