<?php

namespace App\Support;

use App\Models\Branch;
use Illuminate\Http\Request;

/**
 * Resolves branch scope from the request (e.g. X-Branch-Id header).
 * When present and valid, API responses are restricted to that branch (admin/verificador).
 * When absent, no restriction is applied (superadmin sees all).
 */
final class BranchScope
{
    public static function requestedBranchId(?Request $request = null): ?int
    {
        $request = $request ?? request();
        $headerName = config('branch_scope.header_name', 'X-Branch-Id');
        $value = $request->header($headerName);

        if ($value === null || $value === '') {
            return null;
        }

        $branchId = filter_var($value, FILTER_VALIDATE_INT);
        if ($branchId === false || $branchId < 1) {
            return null;
        }

        return Branch::where('branch_id', $branchId)->exists() ? $branchId : null;
    }

    public static function applyBranchFilterToQuery($query, string $branchColumn = 'branch_id'): void
    {
        $branchId = self::requestedBranchId();
        if ($branchId !== null) {
            $query->where($branchColumn, $branchId);
        }
    }
}
