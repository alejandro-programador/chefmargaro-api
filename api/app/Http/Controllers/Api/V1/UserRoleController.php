<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UserRoleResource;
use App\Models\UserRole;
use Illuminate\Http\Request;

class UserRoleController extends Controller
{
    public function index(Request $request)
    {
        $query = UserRole::query();

        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('role_name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $perPage = $request->get('per_page', 15);
        return UserRoleResource::collection($query->paginate($perPage));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'role_name' => ['required', 'string', 'max:50', 'unique:user_roles,role_name'],
            'description' => ['nullable', 'string'],
            'permissions' => ['nullable', 'string'],
        ]);

        $role = UserRole::create($data);

        return response()->json([
            'message' => 'User role created successfully',
            'data' => new UserRoleResource($role),
        ], 201);
    }

    public function show(UserRole $userRole)
    {
        $userRole->load('rolePermissions');
        return new UserRoleResource($userRole);
    }

    public function update(Request $request, UserRole $userRole)
    {
        $data = $request->validate([
            'role_name' => ['sometimes', 'string', 'max:50', 'unique:user_roles,role_name,' . $userRole->role_id . ',role_id'],
            'description' => ['nullable', 'string'],
            'permissions' => ['nullable', 'string'],
        ]);

        $userRole->update($data);

        return response()->json([
            'message' => 'User role updated successfully',
            'data' => new UserRoleResource($userRole),
        ]);
    }

    public function destroy(UserRole $userRole)
    {
        $userRole->delete();
        return response()->noContent();
    }

    public function syncPermissions(Request $request, UserRole $userRole)
    {
        $request->validate([
            'permission_ids' => ['required', 'array'],
            'permission_ids.*' => ['integer', 'exists:permissions,permission_id'],
        ]);

        $userRole->rolePermissions()->sync($request->get('permission_ids'));

        return response()->json([
            'message' => 'Role permissions synced successfully',
            'data' => new UserRoleResource($userRole->load('rolePermissions')),
        ]);
    }
}
