<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UserBranchAccessResource;
use App\Models\UserBranchAccess;
use Illuminate\Http\Request;

class UserBranchAccessController extends Controller
{
    public function index(Request $request)
    {
        $query = UserBranchAccess::with(['user', 'branch']);

        if ($request->has('user_id')) {
            $query->where('user_id', $request->get('user_id'));
        }

        if ($request->has('branch_id')) {
            $query->where('branch_id', $request->get('branch_id'));
        }

        $perPage = $request->get('per_page', 15);
        return UserBranchAccessResource::collection($query->paginate($perPage));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,user_id'],
            'branch_id' => ['required', 'integer', 'exists:branches,branch_id'],
        ]);

        $access = UserBranchAccess::create($data);
        $access->load(['user', 'branch']);

        return response()->json([
            'message' => 'User branch access created successfully',
            'data' => new UserBranchAccessResource($access),
        ], 201);
    }

    public function show(UserBranchAccess $userBranchAccess)
    {
        $userBranchAccess->load(['user', 'branch']);
        return new UserBranchAccessResource($userBranchAccess);
    }

    public function update(Request $request, UserBranchAccess $userBranchAccess)
    {
        $data = $request->validate([
            'user_id' => ['sometimes', 'integer', 'exists:users,user_id'],
            'branch_id' => ['sometimes', 'integer', 'exists:branches,branch_id'],
        ]);

        $userBranchAccess->update($data);
        $userBranchAccess->load(['user', 'branch']);

        return response()->json([
            'message' => 'User branch access updated successfully',
            'data' => new UserBranchAccessResource($userBranchAccess),
        ]);
    }

    public function destroy(UserBranchAccess $userBranchAccess)
    {
        $userBranchAccess->delete();
        return response()->noContent();
    }
}
