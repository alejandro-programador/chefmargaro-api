<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\DeliveryPriceService;
use Illuminate\Http\Request;
use RuntimeException;

class DeliveryPriceController extends Controller
{
    public function __invoke(Request $request, DeliveryPriceService $deliveryPrice)
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'min:1'],
        ]);

        try {
            return response()->json(
                $deliveryPrice->getPriceForPhone($validated['phone'])
            );
        } catch (RuntimeException $e) {
            return response()->json([
                'result' => $e->getMessage(),
            ], 502);
        }
    }
}
