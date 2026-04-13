<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ExtraResource;
use App\Models\Extra;
use App\Support\BranchScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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

    public function store(Request $request)
    {
        $data = $request->validate([
            'branch_id' => ['nullable', 'integer', 'exists:branches,branch_id'],
            'title' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'price_eur' => ['required', 'numeric', 'min:0'],
            'quantity' => ['sometimes', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:5120'],
        ]);

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

    public function update(Request $request, Extra $extra)
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
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:5120'],
        ]);

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
}
