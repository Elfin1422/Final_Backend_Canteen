<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// ── Public ──────────────────────────────────────────────────────────────────
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login',    [AuthController::class, 'login']);

// ── Authenticated ────────────────────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me',      [AuthController::class, 'me']);

    // Categories
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::middleware('role:admin')->group(function () {
        Route::post('/categories',              [CategoryController::class, 'store']);
        Route::put('/categories/{category}',    [CategoryController::class, 'update']);
        Route::delete('/categories/{category}', [CategoryController::class, 'destroy']);
    });

    // Menu
    Route::get('/menu',            [MenuController::class, 'index']);
    Route::get('/menu/{menuItem}', [MenuController::class, 'show']);
    Route::middleware('role:admin')->group(function () {
        Route::post('/menu',                    [MenuController::class, 'store']);
        Route::post('/menu/{menuItem}',         [MenuController::class, 'update']);
        Route::delete('/menu/{menuItem}',       [MenuController::class, 'destroy']);
        Route::patch('/menu/{menuItem}/toggle', [MenuController::class, 'toggleAvailability']);
    });

    // Orders
    Route::get('/orders',         [OrderController::class, 'index']);
    Route::post('/orders',        [OrderController::class, 'store']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    Route::middleware('role:admin,cashier')->group(function () {
        Route::patch('/orders/{order}/status', [OrderController::class, 'updateStatus']);
    });

    // Inventory
    Route::middleware('role:admin')->group(function () {
        Route::get('/inventory',                    [InventoryController::class, 'index']);
        Route::post('/inventory/{menuItem}/adjust', [InventoryController::class, 'adjust']);
        Route::post('/inventory/bulk-restock',      [InventoryController::class, 'bulkRestock']);
        Route::get('/inventory/logs',               [InventoryController::class, 'logs']);
    });

    // Reports
    Route::middleware('role:admin')->prefix('reports')->group(function () {
        Route::get('/summary',            [ReportController::class, 'summary']);
        Route::get('/daily-sales',        [ReportController::class, 'dailySales']);
        Route::get('/weekly-sales',       [ReportController::class, 'weeklySales']);
        Route::get('/top-items',          [ReportController::class, 'topItems']);
        Route::get('/category-breakdown', [ReportController::class, 'categoryBreakdown']);
        Route::get('/order-trend',        [ReportController::class, 'orderTrend']);
    });

    // Users
    Route::middleware('role:admin')->group(function () {
        Route::get('/users',           [UserController::class, 'index']);
        Route::post('/users',          [UserController::class, 'store']);
        Route::put('/users/{user}',    [UserController::class, 'update']);
        Route::delete('/users/{user}', [UserController::class, 'destroy']);
    });
});
