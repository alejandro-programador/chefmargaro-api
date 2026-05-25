<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ComboResource;
use App\Models\Combo;
use App\Services\XetuxCatalogueService;
use App\Support\BranchScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ComboController extends Controller
{
    /**
     * Base URL for files served by Apache/XAMPP (not under /api).
     * When APP_URL ends with /api, public assets live one segment above.
     */
    protected function publicAppBaseUrl(): string
    {
        $base = config('app.public_url');
        if (empty($base)) {
            $base = config('app.url');
        }
        $base = rtrim((string) $base, '/');
        if (str_ends_with($base, '/api')) {
            $base = substr($base, 0, -4);
        }

        return $base;
    }

    /**
     * Full public URL for a path stored on the public disk (e.g. combos/foo.png).
     */
    protected function publicStorageFileUrl(string $relativePathOnPublicDisk): string
    {
        return $this->publicAppBaseUrl().'/storage/app/public/'.$relativePathOnPublicDisk;
    }

    /**
     * Disk-relative path (e.g. combos/foo.png) from a stored image_url.
     */
    protected function diskPathFromImageUrl(?string $imageUrl): ?string
    {
        if (! $imageUrl) {
            return null;
        }
        $path = Str::after($imageUrl, '/storage/app/public/');
        if ($path === $imageUrl) {
            $path = Str::after($imageUrl, '/api/storage/app/public/');
        }
        if ($path === $imageUrl) {
            $path = Str::after($imageUrl, '/storage/');
        }
        if ($path === $imageUrl) {
            return null;
        }

        return $path;
    }

    public function index(Request $request)
    {
        $query = Combo::with('branch');

        $branchId = BranchScope::requestedBranchId($request);
        if ($branchId !== null) {
            $query->where(fn ($q) => $q->whereNull('branch_id')->orWhere('branch_id', $branchId));
        }
        // Filter by branch_id (only if provided and not empty)
        elseif ($request->filled('branch_id')) {
            $query->where('branch_id', $request->get('branch_id'));
        }

        // Filter by is_active (only if provided and not empty)
        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Filter by category (only if provided and not empty)
        if ($request->filled('category')) {
            $query->where('category', $request->get('category'));
        }

        // Search functionality (only if provided and not empty)
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $perPage = $request->get('per_page', 15);
        return ComboResource::collection($query->paginate($perPage));
    }

    public function store(Request $request, XetuxCatalogueService $xetux)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'price_eur' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'category' => ['required', 'string', 'in:rolls-mixtos,pollo-crispy'],
            'rolls_count' => ['required_if:category,rolls-mixtos', 'nullable', 'integer', 'min:1', 'max:99'],
            'includes_drink' => ['sometimes', 'boolean'],
            'has_topping' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'branch_id' => ['sometimes', 'integer', 'exists:branches,branch_id'],
            'xetux_product_id' => ['required', 'integer', Rule::unique('combos', 'xetux_product_id')],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:5120'],
        ]);

        $data = array_merge($data, $this->resolveXetuxFields($xetux, (int) $data['xetux_product_id']));

        if (($data['category'] ?? '') !== 'rolls-mixtos') {
            $data['rolls_count'] = null;
            $data['has_topping'] = false;
        } else {
            $data['has_topping'] = (bool) ($data['has_topping'] ?? false);
        }
        $data['includes_drink'] = (bool) ($data['includes_drink'] ?? false);

        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('combos', 'public');
            $data['image_url'] = $this->publicStorageFileUrl($imagePath);
        }

        $combo = Combo::create($data);
        $combo->load('branch');

        return response()->json([
            'message' => 'Combo created successfully',
            'data' => new ComboResource($combo),
        ], 201);
    }

    public function show(Combo $combo)
    {
        $branchId = BranchScope::requestedBranchId();
        if ($branchId !== null && $combo->branch_id !== null && (int) $combo->branch_id !== $branchId) {
            abort(404);
        }
        $combo->load(['branch', 'extras']);
        return new ComboResource($combo);
    }

    public function update(Request $request, Combo $combo, XetuxCatalogueService $xetux)
    {
        $branchId = BranchScope::requestedBranchId();
        if ($branchId !== null && $combo->branch_id !== null && (int) $combo->branch_id !== $branchId) {
            abort(404);
        }
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'price_eur' => ['sometimes', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'category' => ['sometimes', 'string', 'in:rolls-mixtos,pollo-crispy'],
            'rolls_count' => ['required_if:category,rolls-mixtos', 'nullable', 'integer', 'min:1', 'max:99'],
            'includes_drink' => ['sometimes', 'boolean'],
            'has_topping' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'branch_id' => ['sometimes', 'integer', 'exists:branches,branch_id'],
            'xetux_product_id' => [
                'sometimes',
                'integer',
                Rule::unique('combos', 'xetux_product_id')->ignore($combo->combo_id, 'combo_id'),
            ],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:5120'],
        ]);

        if (array_key_exists('xetux_product_id', $data)) {
            $data = array_merge(
                $data,
                $this->resolveXetuxFields($xetux, (int) $data['xetux_product_id'], $combo->combo_id)
            );
        }

        $category = $data['category'] ?? $combo->category;
        if ($category !== 'rolls-mixtos') {
            $data['rolls_count'] = null;
            $data['has_topping'] = false;
        } elseif (array_key_exists('has_topping', $data)) {
            $data['has_topping'] = (bool) $data['has_topping'];
        }
        if (array_key_exists('includes_drink', $data)) {
            $data['includes_drink'] = (bool) $data['includes_drink'];
        }

        if ($request->hasFile('image')) {
            $existingPath = $this->diskPathFromImageUrl($combo->image_url);
            if ($existingPath) {
                Storage::disk('public')->delete($existingPath);
            }
            $imagePath = $request->file('image')->store('combos', 'public');
            $data['image_url'] = $this->publicStorageFileUrl($imagePath);
        }

        $combo->update($data);
        $combo->load('branch');

        return response()->json([
            'message' => 'Combo updated successfully',
            'data' => new ComboResource($combo),
        ]);
    }

    public function destroy(Combo $combo)
    {
        $branchId = BranchScope::requestedBranchId();
        if ($branchId !== null && $combo->branch_id !== null && (int) $combo->branch_id !== $branchId) {
            abort(404);
        }
        $path = $this->diskPathFromImageUrl($combo->image_url);
        if ($path) {
            Storage::disk('public')->delete($path);
        }
        $combo->delete();
        return response()->noContent();
    }

    public function syncExtras(Request $request, Combo $combo)
    {
        $branchId = BranchScope::requestedBranchId($request);
        if ($branchId !== null && $combo->branch_id !== null && (int) $combo->branch_id !== $branchId) {
            abort(404);
        }

        $request->validate([
            'extra_ids' => ['present', 'array'],
            'extra_ids.*' => ['integer', 'exists:extras,extra_id'],
        ]);

        $extraIds = $request->input('extra_ids', []);
        $syncData = [];
        foreach ($extraIds as $index => $extraId) {
            $syncData[(int) $extraId] = ['sort_order' => (int) $index];
        }

        $combo->extras()->sync($syncData);
        $combo->load(['branch', 'extras']);

        return response()->json([
            'message' => 'Combo extras synced successfully',
            'data' => new ComboResource($combo),
        ]);
    }

    /**
     * @return array{xetux_product_id: int, xetux_item_id: int, xetux_family_id: int}
     */
    protected function resolveXetuxFields(
        XetuxCatalogueService $xetux,
        int $xetuxProductId,
        ?int $excludeComboId = null
    ): array {
        $match = collect($xetux->comboLinkableProducts($excludeComboId))
            ->firstWhere('product_id', $xetuxProductId);

        if (! $match) {
            throw ValidationException::withMessages([
                'xetux_product_id' => ['El producto Xetux seleccionado no es válido para combos.'],
            ]);
        }

        if ($match['is_linked']) {
            throw ValidationException::withMessages([
                'xetux_product_id' => ['Ese producto Xetux ya está vinculado a otro combo.'],
            ]);
        }

        return [
            'xetux_product_id' => $xetuxProductId,
            'xetux_item_id' => (int) $match['item_id'],
            'xetux_family_id' => (int) $match['family_id'],
        ];
    }
}
