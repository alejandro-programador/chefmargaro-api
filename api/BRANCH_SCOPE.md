# Branch scope (data filtering by role)

## Overview

- **superadmin**: Sees all data; do not send `X-Branch-Id` (or leave `branch_id` unset in the client).
- **admin**: Has all permissions; data is restricted to the branch assigned to the user. Send `X-Branch-Id` with the user’s branch ID on every request.
- **verificador**: Has permissions for orders, payments, and customers only; data is restricted to the assigned branch. Send `X-Branch-Id` with the user’s branch ID.

## Backend

- **Config**: `config/branch_scope.php` — header name (default `X-Branch-Id`) and role/permission reference.
- **Helper**: `App\Support\BranchScope::requestedBranchId(Request $request)` — returns the validated branch ID from the header, or `null` if missing/invalid.
- **Endpoints**: Orders, payments, customers, products, extras, combos, and branches apply a branch filter when `X-Branch-Id` is present and valid. For `show`/`update`/`destroy`, access is denied (404) if the resource does not belong to that branch.

## Frontend

- Store the user’s `branch_id` (e.g. from login or user profile, from `user_branch_access` or equivalent) in `localStorage` or `sessionStorage` under the key `branch_id` for admin and verificador.
- Do not set `branch_id` for superadmin so that no `X-Branch-Id` header is sent and the API returns all data.
- The axios client sends the `X-Branch-Id` header when `branch_id` is present in storage.

## Running the SQL seeds

1. Run `seed_permissions_menu.sql` so the `permissions` table is populated.
2. Run `seed_roles_superadmin_admin_verificador.sql` to create the three roles and assign permissions via `role_permission`.
3. Ensure `user_roles` has a UNIQUE on `role_name` and `role_permission` has a UNIQUE on `(role_id, permission_id)` for idempotent inserts. If `user_roles` has no `updated_at` column, adjust the INSERT to omit it.
