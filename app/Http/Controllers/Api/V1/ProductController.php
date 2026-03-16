<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreProductRequest;
use App\Http\Requests\Api\V1\UpdateProductRequest;
use App\Http\Resources\Api\V1\ProductResource;
use App\Models\Product;
use App\Support\BranchScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Product::with('branch');

        $branchId = BranchScope::requestedBranchId($request);
        if ($branchId !== null) {
            $query->where(fn ($q) => $q->whereNull('branch_id')->orWhere('branch_id', $branchId));
        }
        // Filter by branch_id (only if provided and not empty)
        elseif ($request->filled('branch_id')) {
            $query->where(fn ($q) => $q->whereNull('branch_id')->orWhere('branch_id', $request->get('branch_id')));
        }

        // Filter by is_active (only if provided and not empty)
        if ($request->has('is_active') && $request->get('is_active') !== '') {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Search functionality (only if provided and not empty)
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Ordering
        $orderBy = $request->get('order_by', 'created_at');
        $order = $request->get('order', 'desc');
        $query->orderBy($orderBy, $order);

        $perPage = $request->get('per_page', 15);
        $products = $query->paginate($perPage);

        return ProductResource::collection($products);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProductRequest $request)
    {
        $data = $request->validated();

        // Handle image upload
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imagePath = $image->store('products', 'public');
            $data['image_url'] = Storage::url($imagePath);
        }

        $product = Product::create($data);
        $product->load('branch');

        return response()->json([
            'message' => 'Product created successfully',
            'data' => new ProductResource($product),
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Product $product)
    {
        $branchId = BranchScope::requestedBranchId();
        if ($branchId !== null && $product->branch_id !== null && (int) $product->branch_id !== $branchId) {
            abort(404);
        }
        $product->load(['branch', 'extras']);
        return new ProductResource($product);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProductRequest $request, Product $product)
    {
        $branchId = BranchScope::requestedBranchId();
        if ($branchId !== null && $product->branch_id !== null && (int) $product->branch_id !== $branchId) {
            abort(404);
        }
        $data = $request->validated();

        // Handle image upload
        if ($request->hasFile('image')) {
            // Delete old image if exists
            if ($product->image_url) {
                Storage::disk('public')->delete(str_replace('/storage/', '', $product->image_url));
            }
            
            $image = $request->file('image');
            $imagePath = $image->store('products', 'public');
            $data['image_url'] = Storage::url($imagePath);
        }

        $product->update($data);
        $product->load('branch');

        return response()->json([
            'message' => 'Product updated successfully',
            'data' => new ProductResource($product),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product $product)
    {
        $branchId = BranchScope::requestedBranchId();
        if ($branchId !== null && $product->branch_id !== null && (int) $product->branch_id !== $branchId) {
            abort(404);
        }
        // Delete image if exists
        if ($product->image_url) {
            Storage::disk('public')->delete(str_replace('/storage/', '', $product->image_url));
        }

        $product->delete();

        return response()->noContent();
    }

    /**
     * Sync extras for a product.
     */
    public function syncExtras(Request $request, Product $product)
    {
        $request->validate([
            'extra_ids' => ['required', 'array'],
            'extra_ids.*' => ['integer', 'exists:extras,extra_id'],
        ]);

        $extraIds = $request->get('extra_ids');
        $syncData = [];
        
        foreach ($extraIds as $index => $extraId) {
            $syncData[$extraId] = ['sort_order' => $index];
        }

        $product->extras()->sync($syncData);
        $product->load('extras');

        return response()->json([
            'message' => 'Product extras synced successfully',
            'data' => new ProductResource($product),
        ]);
    }
}
