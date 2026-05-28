<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\BcvExchangeRateService;
use RuntimeException;

class BcvExchangeRateController extends Controller
{
    /**
     * GET /api/v1/bcv
     *
     * Equivalente al endpoint Node /api-bcv del proyecto api-bcv.
     */
    public function __invoke(BcvExchangeRateService $bcvExchangeRate)
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $bcvExchangeRate->fetch(),
            ]);
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
