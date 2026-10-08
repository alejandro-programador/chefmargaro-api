<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ExtraResource;
use App\Models\Extra;
use App\Services\XetuxCatalogueService;
use App\Support\BranchScope;
use App\Support\PublicStorageUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ExtraController extends Controller
{
    public function index(Request $request, XetuxCatalogueService $xetux)
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
        $extras = $query->paginate($perPage);
        $this->attachDrinkFlavors($extras->getCollection(), $xetux);

        return ExtraResource::collection($extras);
    }

    /**
     * Crea un extra por cada familia de bebida (Agua, Té, refrescos y jugos).
     */
    public function syncDrinkFamilies(XetuxCatalogueService $xetux)
    {
        try {
            $catalogue = $xetux->fetchCatalogue();
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 502);
        }

        $extras = collect();
        foreach (config('xetux.drink_extra_families', []) as $familyId => $meta) {
            $familyId = (int) $familyId;
            $flavors = $xetux->flavorsForFamily($familyId, $catalogue);
            $extra = Extra::query()
                ->whereNull('xetux_product_id')
                ->where('xetux_family_id', $familyId)
                ->first();

            if (! $extra) {
                $minPrice = collect($flavors)->min('price');
                $extra = Extra::create([
                    'branch_id' => null,
                    'title' => (string) ($meta['title'] ?? 'Bebida'),
                    'description' => (string) ($meta['description'] ?? 'Elige el sabor.'),
                    'price_eur' => $minPrice !== null ? round((float) $minPrice, 2) : 0,
                    'quantity' => 1,
                    'is_active' => true,
                    'xetux_product_id' => null,
                    'xetux_item_id' => null,
                    'xetux_family_id' => $familyId,
                ]);
            }

            $extra->flavorOptions = $flavors;
            $extras->push($extra);
        }

        return response()->json([
            'message' => 'Extras de bebidas listos',
            'data' => ExtraResource::collection($extras),
        ]);
    }

    public function store(Request $request, XetuxCatalogueService $xetux)
    {
        $isFamily = $request->filled('xetux_family_id');
        $rules = [
            'branch_id' => ['nullable', 'integer', 'exists:branches,branch_id'],
            'title' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'price_eur' => ['required', 'numeric', 'min:0'],
            'quantity' => ['sometimes', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:5120'],
        ];
        if ($isFamily) {
            $rules['xetux_family_id'] = ['required', 'integer'];
        } else {
            $rules['xetux_product_id'] = ['required', 'integer', Rule::unique('extras', 'xetux_product_id')];
        }

        $data = $request->validate($rules);
        $data = array_merge($data, $isFamily
            ? $this->resolveFamilyFields($xetux, (int) $data['xetux_family_id'])
            : $this->resolveXetuxFields($xetux, (int) $data['xetux_product_id']));

        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('extras', 'public');
            $data['image_url'] = PublicStorageUrl::absoluteUrl($imagePath);
        }

        $extra = Extra::create($data);
        return response()->json([
            'message' => 'Extra created successfully',
            'data' => new ExtraResource($extra),
        ], 201);
    }

    public function show(Request $request, Extra $extra, XetuxCatalogueService $xetux)
    {
        $branchId = BranchScope::requestedBranchId($request);
        if ($branchId !== null && $extra->branch_id !== null && (int) $extra->branch_id !== $branchId) {
            abort(404);
        }
        $extra->load('products');
        $this->attachDrinkFlavors(collect([$extra]), $xetux);
        return new ExtraResource($extra);
    }

    public function update(Request $request, Extra $extra, XetuxCatalogueService $xetux)
    {
        $branchId = BranchScope::requestedBranchId($request);
        if ($branchId !== null && $extra->branch_id !== null && (int) $extra->branch_id !== $branchId) {
            abort(404);
        }
        $isFamily = $request->filled('xetux_family_id');
        $rules = [
            'branch_id' => ['nullable', 'integer', 'exists:branches,branch_id'],
            'title' => ['sometimes', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'price_eur' => ['sometimes', 'numeric', 'min:0'],
            'quantity' => ['sometimes', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:5120'],
        ];
        if ($isFamily) {
            $rules['xetux_family_id'] = ['required', 'integer'];
        } else {
            $rules['xetux_product_id'] = [
                'sometimes',
                'integer',
                Rule::unique('extras', 'xetux_product_id')->ignore($extra->extra_id, 'extra_id'),
            ];
        }

        $data = $request->validate($rules);

        if ($isFamily) {
            $data = array_merge(
                $data,
                $this->resolveFamilyFields($xetux, (int) $data['xetux_family_id'], $extra->extra_id)
            );
        } elseif (array_key_exists('xetux_product_id', $data)) {
            $data = array_merge(
                $data,
                $this->resolveXetuxFields($xetux, (int) $data['xetux_product_id'], $extra->extra_id)
            );
        }

        if ($request->hasFile('image')) {
            $existingPath = PublicStorageUrl::diskPathFromStored($extra->image_url);
            if ($existingPath) {
                Storage::disk('public')->delete($existingPath);
            }
            $imagePath = $request->file('image')->store('extras', 'public');
            $data['image_url'] = PublicStorageUrl::absoluteUrl($imagePath);
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
        $path = PublicStorageUrl::diskPathFromStored($extra->image_url);
        if ($path) {
            Storage::disk('public')->delete($path);
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

    /**
     * @return array{xetux_product_id: null, xetux_item_id: null, xetux_family_id: int}
     */
    protected function resolveFamilyFields(
        XetuxCatalogueService $xetux,
        int $familyId,
        ?int $excludeExtraId = null
    ): array {
        if (! array_key_exists($familyId, config('xetux.drink_extra_families', []))) {
            throw ValidationException::withMessages([
                'xetux_family_id' => ['Esa familia no está habilitada como extra de bebida.'],
            ]);
        }

        $alreadyLinked = Extra::query()
            ->whereNull('xetux_product_id')
            ->where('xetux_family_id', $familyId)
            ->when($excludeExtraId !== null, fn ($query) => $query->where('extra_id', '!=', $excludeExtraId))
            ->exists();

        if ($alreadyLinked) {
            throw ValidationException::withMessages([
                'xetux_family_id' => ['Esa familia ya tiene un extra de bebida.'],
            ]);
        }

        try {
            $flavors = $xetux->flavorsForFamily($familyId);
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages([
                'xetux_family_id' => [$e->getMessage()],
            ]);
        }

        if ($flavors === []) {
            throw ValidationException::withMessages([
                'xetux_family_id' => ['Esa familia no tiene productos en el catálogo de Xetux.'],
            ]);
        }

        return [
            'xetux_product_id' => null,
            'xetux_item_id' => null,
            'xetux_family_id' => $familyId,
        ];
    }

    /**
     * @param  Collection<int, Extra>  $extras
     */
    protected function attachDrinkFlavors(Collection $extras, XetuxCatalogueService $xetux): void
    {
        $familyExtras = $extras->filter(fn (Extra $extra) => $extra->isDrinkFamily());
        if ($familyExtras->isEmpty()) {
            return;
        }

        try {
            $catalogue = $xetux->fetchCatalogue();
        } catch (RuntimeException) {
            return;
        }

        foreach ($familyExtras as $extra) {
            $extra->flavorOptions = $xetux->flavorsForFamily((int) $extra->xetux_family_id, $catalogue);
        }
    }
}
