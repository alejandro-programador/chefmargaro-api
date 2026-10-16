<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreBranchRequest;
use App\Http\Requests\Api\V1\UpdateBranchRequest;
use App\Http\Resources\Api\V1\BranchResource;
use App\Models\Branch;
use App\Support\BranchScope;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Branch::query();

        $branchId = BranchScope::requestedBranchId($request);
        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        // Search functionality (only if provided and not empty)
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('address', 'like', "%{$search}%");
            });
        }

        $perPage = $request->get('per_page', 15);
        $branches = $query->paginate($perPage);

        return BranchResource::collection($branches);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreBranchRequest $request)
    {
        $branch = Branch::create($request->validated());

        return response()->json([
            'message' => 'Branch created successfully',
            'data' => new BranchResource($branch),
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Branch $branch)
    {
        $branchId = BranchScope::requestedBranchId();
        if ($branchId !== null && (int) $branch->branch_id !== $branchId) {
            abort(404);
        }
        return new BranchResource($branch);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateBranchRequest $request, Branch $branch)
    {
        $branchId = BranchScope::requestedBranchId();
        if ($branchId !== null && (int) $branch->branch_id !== $branchId) {
            abort(404);
        }
        $branch->update($request->validated());

        return response()->json([
            'message' => 'Branch updated successfully',
            'data' => new BranchResource($branch),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Branch $branch)
    {
        $branchId = BranchScope::requestedBranchId();
        if ($branchId !== null && (int) $branch->branch_id !== $branchId) {
            abort(404);
        }
        $branch->delete();

        return response()->noContent();
    }
}
