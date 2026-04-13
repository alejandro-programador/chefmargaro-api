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
     * UI permission slugs used by admin_panel sidebar.
     *
     * @var array<int, string>
     */
    protected array $allUiPermissions = [
        'dashboard',
        'orders',
        'payments',
        'branches',
        'logs',
        'extras',
        'customers',
        'combos',
        'users',
        'permissions',
        'roles',
        'user_roles',
    ];

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

        $permissions = [];

        // Prefer user_roles.permissions column when present (JSON, CSV, or "all")
        if (! empty($role->permissions)) {
            $raw = trim((string) $role->permissions);
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $permissions = array_values(array_unique(array_map('strval', $decoded)));
            } else {
                // Accept plain text lists: "orders,payments" or "all"
                $permissions = array_values(array_filter(array_map('trim', explode(',', $raw))));
            }
        }

        // Fallback to rolePermissions relation (slugs)
        if (empty($permissions) && $role->relationLoaded('rolePermissions')) {
            $permissions = $role->rolePermissions->pluck('slug')->filter()->values()->all();
        }

        return $this->normalizePermissions($permissions, $role->role_name ?? null);
    }

    /**
     * Normalize role permissions to the slugs expected by admin_panel.
     *
     * @param array<int, string> $permissions
     * @return array<int, string>
     */
    protected function normalizePermissions(array $permissions, ?string $roleName = null): array
    {
        $permissions = array_values(array_unique(array_map(static fn ($p) => strtolower(trim((string) $p)), $permissions)));

        $roleName = strtolower((string) $roleName);
        if ($roleName === 'admin' || $roleName === 'superadmin' || in_array('all', $permissions, true) || in_array('*', $permissions, true)) {
            return $this->allUiPermissions;
        }

        // Role-based defaults for legacy/non-normalized permission payloads.
        // These are applied in addition to explicit permissions.
        $defaultByRole = [
            'verifier' => ['dashboard', 'payments'],
            'manager' => ['dashboard', 'orders', 'combos', 'extras', 'customers'],
        ];

        $mapping = [
            'verify_payments' => ['payments'],
            'manage_orders' => ['orders', 'dashboard'],
            'manage_products' => ['extras', 'combos', 'customers'],
            'manage_users' => ['users', 'user_roles'],
            'view_reports' => ['logs', 'dashboard'],
        ];

        $uiPermissions = $defaultByRole[$roleName] ?? [];
        foreach ($permissions as $permission) {
            if (isset($mapping[$permission])) {
                $uiPermissions = array_merge($uiPermissions, $mapping[$permission]);
                continue;
            }

            // Keep direct UI slugs if they were already stored as-is
            if (in_array($permission, $this->allUiPermissions, true)) {
                $uiPermissions[] = $permission;
            }
        }

        return array_values(array_unique($uiPermissions));
    }
}

