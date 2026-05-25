<?php

use App\Http\Controllers\Api\V1\BranchController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\ExtraController;
use App\Http\Controllers\Api\V1\ComboController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\PaymentVerificationController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\UserRoleController;
use App\Http\Controllers\Api\V1\UserBranchAccessController;
use App\Http\Controllers\Api\V1\LogController;
use App\Http\Controllers\Api\V1\PermissionController;
use App\Http\Controllers\Api\V1\XetuxCatalogueController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Auth
    Route::post('auth/login', [AuthController::class, 'login']);

    Route::get('xetux/catalogue/combo-products', [XetuxCatalogueController::class, 'comboProducts']);
    Route::get('xetux/catalogue/extra-products', [XetuxCatalogueController::class, 'extraProducts']);

    // Branches
    Route::apiResource('branches', BranchController::class);
    
    // Customers
    Route::apiResource('customers', CustomerController::class);
    
    // Products
    Route::apiResource('products', ProductController::class);
    Route::put('products/{product}/extras', [ProductController::class, 'syncExtras']);
    
    // Extras
    Route::apiResource('extras', ExtraController::class);
    
    // Combos
    Route::apiResource('combos', ComboController::class);
    Route::put('combos/{combo}/extras', [ComboController::class, 'syncExtras']);
    
    // Orders
    Route::apiResource('orders', OrderController::class);
    Route::post('orders/{order}/generate-tracking-link', [OrderController::class, 'generateTrackingLink']);
    
    // Public order tracking endpoint (no authentication required)
    Route::get('orders/tracking/{trackingToken}', [OrderController::class, 'showByTrackingToken']);
    
    // Payments
    Route::apiResource('payments', PaymentController::class);
    
    // Payment Verifications
    Route::apiResource('payment-verifications', PaymentVerificationController::class);
    
    // Users
    Route::apiResource('users', UserController::class);
    
    // User Roles
    Route::apiResource('user-roles', UserRoleController::class);
    Route::put('user-roles/{userRole}/permissions', [UserRoleController::class, 'syncPermissions']);
    
    // User Branch Access
    Route::apiResource('user-branch-access', UserBranchAccessController::class);
    
    // Logs (index, show, store for CRUD action tracking)
    Route::apiResource('logs', LogController::class)->only(['index', 'show', 'store']);
    
    // Permissions
    Route::apiResource('permissions', PermissionController::class);
});

