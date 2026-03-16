<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Authentication controller for API login.
 * Issues a simple token and returns user data plus role permissions.
 */
class AuthController extends Controller
{
    /**
     * Login with email and password.
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::with('role.rolePermissions')->where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password ?? $user->password_hash)) {
            return response()->json([
                'message' => 'Invalid credentials.',
                'errors' => [
                    'email' => ['Las credenciales no son válidas.'],
                ],
            ], 422);
        }

        // Update last_login for auditing
        $user->last_login = now();
        $user->save();

        $permissions = $this->resolvePermissionsFromRole($user);

        // Simple opaque token for client-side auth (not used for API authorization yet)
        $token = Str::random(60);

        return response()->json([
            'message' => 'Login successful',
            'data' => [
                'token' => $token,
                'user' => new UserResource($user->load('role', 'branch')),
                'permissions' => $permissions,
            ],
        ]);
    }

    /**
     * Resolve permission slugs from the user's role.
     *
     * @return array<int, string>
     */
    protected function resolvePermissionsFromRole(User $user): array
    {
        $role = $user->role;
        if (! $role) {
            return [];
        }

        // Prefer JSON column on user_roles.permissions if present
        if (! empty($role->permissions)) {
            $decoded = json_decode($role->permissions, true);
            if (is_array($decoded)) {
                return array_values(array_unique(array_map('strval', $decoded)));
            }
        }

        // Fallback to rolePermissions relation (slugs)
        if ($role->relationLoaded('rolePermissions')) {
            return $role->rolePermissions->pluck('slug')->values()->all();
        }

        return [];
    }
}

