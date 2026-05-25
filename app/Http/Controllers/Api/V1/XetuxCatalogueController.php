<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\XetuxCatalogueService;
use Illuminate\Http\Request;
use RuntimeException;

class XetuxCatalogueController extends Controller
{
    public function comboProducts(Request $request, XetuxCatalogueService $xetux)
    {
        try {
            $excludeComboId = $request->filled('exclude_combo_id')
                ? (int) $request->get('exclude_combo_id')
                : null;

            $products = $xetux->comboLinkableProducts($excludeComboId);

            return response()->json([
                'message' => 'Catálogo Xetux para combos',
                'data' => [
                    'products' => $products,
                    'linked_product_ids' => collect($products)
                        ->filter(fn ($p) => $p['is_linked'])
                        ->pluck('product_id')
                        ->values()
                        ->all(),
                ],
            ]);
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 502);
        }
    }

    public function extraProducts(Request $request, XetuxCatalogueService $xetux)
    {
        try {
            $excludeExtraId = $request->filled('exclude_extra_id')
                ? (int) $request->get('exclude_extra_id')
                : null;

            $products = $xetux->extraLinkableProducts($excludeExtraId);

            return response()->json([
                'message' => 'Catálogo Xetux para extras',
                'data' => [
                    'products' => $products,
                    'linked_product_ids' => collect($products)
                        ->filter(fn ($p) => $p['is_linked'])
                        ->pluck('product_id')
                        ->values()
                        ->all(),
                ],
            ]);
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 502);
        }
    }
}
