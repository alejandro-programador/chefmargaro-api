<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\LogResource;
use App\Models\Log;
use App\Support\BranchScope;
use Illuminate\Http\Request;

class LogController extends Controller
{
    /**
     * Display a listing of the resource (read-only).
     */
    public function index(Request $request)
    {
        $query = Log::with('user');

        $branchId = BranchScope::requestedBranchId($request);
        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $request->get('user_id'));
        }

        if ($request->has('date_from')) {
            $query->whereDate('timestamp', '>=', $request->get('date_from'));
        }

        if ($request->has('date_to')) {
            $query->whereDate('timestamp', '<=', $request->get('date_to'));
        }

        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where('action_description', 'like', "%{$search}%");
        }

        $perPage = $request->get('per_page', 15);
        return LogResource::collection($query->orderBy('timestamp', 'desc')->paginate($perPage));
    }

    /**
     * Display the specified resource (read-only).
     */
    public function show(Request $request, Log $log)
    {
        $branchId = BranchScope::requestedBranchId($request);
        if ($branchId !== null && (int) ($log->branch_id ?? 0) !== $branchId) {
            abort(404);
        }
        $log->load('user');
        return new LogResource($log);
    }

    /**
     * Store a new log entry (for CRUD action tracking from frontend).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'action_description' => ['required', 'string', 'max:500'],
            'user_id' => ['nullable', 'integer', 'exists:users,user_id'],
        ]);

        $branchId = BranchScope::requestedBranchId($request);

        $log = Log::create([
            'user_id' => $validated['user_id'] ?? null,
            'branch_id' => $branchId,
            'action_description' => $validated['action_description'],
            'timestamp' => now(),
        ]);

        return response()->json([
            'message' => 'Log created successfully',
            'data' => new LogResource($log->load('user')),
        ], 201);
    }
}
