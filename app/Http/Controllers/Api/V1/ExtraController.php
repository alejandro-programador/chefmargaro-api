<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ExtraResource;
use App\Models\Extra;
use App\Services\XetuxCatalogueService;
use App\Support\BranchScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ExtraController extends Controller
{
    public function index(Request $request)
    {
        $query = Extra::query();

        $branchId = BranchScope::requestedBranchId($request);
        if ($branchId !== null) {
            $query->where(fn ($q) => $q->whereNull('branch_id')->orWhere('branch_id', $branchId));
        }
        // Filter by branch_id (only if provided and not empty)
        elseif ($request->filled('branch_id')) {
            $query->where('branch_id', $request->get('branch_id'));
        }

        // Filter by is_active (only if provided and not empty)
        if ($request->has('is_active') && $request->get('is_active') !== '') {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Search functionality (only if provided and not empty)
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $perPage = $request->get('per_page', 15);
        return ExtraResource::collection($query->paginate($perPage));
    }

    public function store(Request $request, XetuxCatalogueService $xetux)
    {
        $data = $request->validate([
            'branch_id' => ['nullable', 'integer', 'exists:branches,branch_id'],
            'title' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'price_eur' => ['required', 'numeric', 'min:0'],
            'quantity' => ['sometimes', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
            'xetux_product_id' => ['required', 'integer', Rule::unique('extras', 'xetux_product_id')],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:5120'],
        ]);

        $data = array_merge($data, $this->resolveXetuxFields($xetux, (int) $data['xetux_product_id']));

        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('extras', 'public');
            $data['image_url'] = '/storage/app/public/' . $imagePath;
        }

        $extra = Extra::create($data);
        return response()->json([
            'message' => 'Extra created successfully',
            'data' => new ExtraResource($extra),
        ], 201);
    }

    public function show(Request $request, Extra $extra)
    {
        $branchId = BranchScope::requestedBranchId($request);
        if ($branchId !== null && $extra->branch_id !== null && (int) $extra->branch_id !== $branchId) {
            abort(404);
        }
        $extra->load('products');
        return new ExtraResource($extra);
    }

    public function update(Request $request, Extra $extra, XetuxCatalogueService $xetux)
    {
        $branchId = BranchScope::requestedBranchId($request);
        if ($branchId !== null && $extra->branch_id !== null && (int) $extra->branch_id !== $branchId) {
            abort(404);
        }
        $data = $request->validate([
            'branch_id' => ['nullable', 'integer', 'exists:branches,branch_id'],
            'title' => ['sometimes', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'price_eur' => ['sometimes', 'numeric', 'min:0'],
            'quantity' => ['sometimes', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
            'xetux_product_id' => [
                'sometimes',
                'integer',
                Rule::unique('extras', 'xetux_product_id')->ignore($extra->extra_id, 'extra_id'),
            ],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:5120'],
        ]);

        if (array_key_exists('xetux_product_id', $data)) {
            $data = array_merge(
                $data,
                $this->resolveXetuxFields($xetux, (int) $data['xetux_product_id'], $extra->extra_id)
            );
        }

        if ($request->hasFile('image')) {
            if ($extra->image_url) {
                $path = Str::after($extra->image_url, '/storage/app/public/');
                if ($path === $extra->image_url) {
                    $path = Str::after($extra->image_url, '/storage/');
                }
                if ($path !== $extra->image_url) {
                    Storage::disk('public')->delete($path);
                }
            }
            $imagePath = $request->file('image')->store('extras', 'public');
            $data['image_url'] = '/storage/app/public/' . $imagePath;
        }

        $extra->update($data);
        return response()->json([
            'message' => 'Extra updated successfully',
            'data' => new ExtraResource($extra),
        ]);
    }

    public function destroy(Request $request, Extra $extra)
    {
        $branchId = BranchScope::requestedBranchId($request);
        if ($branchId !== null && $extra->branch_id !== null && (int) $extra->branch_id !== $branchId) {
            abort(404);
        }
        if ($extra->image_url) {
            $path = Str::after($extra->image_url, '/storage/app/public/');
            if ($path === $extra->image_url) {
                $path = Str::after($extra->image_url, '/storage/');
            }
            if ($path !== $extra->image_url) {
                Storage::disk('public')->delete($path);
            }
        }
        $extra->delete();
        return response()->noContent();
    }

    /**
     * @return array{xetux_product_id: int, xetux_item_id: int, xetux_family_id: int}
     */
    protected function resolveXetuxFields(
        XetuxCatalogueService $xetux,
        int $xetuxProductId,
        ?int $excludeExtraId = null
    ): array {
        $match = collect($xetux->extraLinkableProducts($excludeExtraId))
            ->firstWhere('product_id', $xetuxProductId);

        if (! $match) {
            throw ValidationException::withMessages([
                'xetux_product_id' => ['El producto Xetux seleccionado no es válido para extras.'],
            ]);
        }

        if ($match['is_linked']) {
            throw ValidationException::withMessages([
                'xetux_product_id' => ['Ese producto Xetux ya está vinculado a otro extra.'],
            ]);
        }

        return [
            'xetux_product_id' => $xetuxProductId,
            'xetux_item_id' => (int) $match['item_id'],
            'xetux_family_id' => (int) $match['family_id'],
        ];
    }
}
