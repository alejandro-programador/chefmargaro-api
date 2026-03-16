<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use App\Support\BranchScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::with('role');

        $branchId = BranchScope::requestedBranchId($request);
        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->has('role_id')) {
            $query->where('role_id', $request->get('role_id'));
        }

        $perPage = $request->get('per_page', 15);
        return UserResource::collection($query->paginate($perPage));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'user_type' => ['required', 'string', 'max:20'],
            'role_id' => ['nullable', 'integer', 'exists:user_roles,role_id'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,branch_id'],
        ]);

        $data['password_hash'] = Hash::make($data['password']);
        $data['password'] = Hash::make($data['password']);
        unset($data['password_confirmation']);

        $user = User::create($data);
        $user->load('role');

        return response()->json([
            'message' => 'User created successfully',
            'data' => new UserResource($user),
        ], 201);
    }

    public function show(Request $request, User $user)
    {
        $branchId = BranchScope::requestedBranchId($request);
        if ($branchId !== null && (int) ($user->branch_id ?? 0) !== $branchId) {
            abort(404);
        }
        $user->load('role');
        return new UserResource($user);
    }

    public function update(Request $request, User $user)
    {
        $branchId = BranchScope::requestedBranchId($request);
        if ($branchId !== null && (int) ($user->branch_id ?? 0) !== $branchId) {
            abort(404);
        }
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'email' => ['sometimes', 'email', 'max:150', 'unique:users,email,' . $user->user_id . ',user_id'],
            'password' => ['sometimes', 'string', 'min:8', 'confirmed'],
            'user_type' => ['sometimes', 'string', 'max:20'],
            'role_id' => ['nullable', 'integer', 'exists:user_roles,role_id'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,branch_id'],
        ]);

        if (isset($data['password'])) {
            $data['password_hash'] = Hash::make($data['password']);
            $data['password'] = Hash::make($data['password']);
            unset($data['password_confirmation']);
        }

        $user->update($data);
        $user->load('role');

        return response()->json([
            'message' => 'User updated successfully',
            'data' => new UserResource($user),
        ]);
    }

    public function destroy(Request $request, User $user)
    {
        $branchId = BranchScope::requestedBranchId($request);
        if ($branchId !== null && (int) ($user->branch_id ?? 0) !== $branchId) {
            abort(404);
        }
        $user->delete();
        return response()->noContent();
    }
}
