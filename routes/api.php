<?php

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\MaterialController;
use App\Http\Controllers\API\FinishController;
use App\Http\Controllers\API\OrderController;
use App\Http\Controllers\API\AdminController;
use Illuminate\Support\Facades\Route;

// ── Públicas ──────────────────────────────────────────────────────
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login',    [AuthController::class, 'login']);
});

Route::get('materials',              [MaterialController::class, 'index']);
Route::get('materials/{material}',   [MaterialController::class, 'show']);
Route::get('finishes',               [FinishController::class,  'index']);

// ── Autenticadas ──────────────────────────────────────────────────
Route::middleware('auth:api')->group(function () {
    Route::post('/auth/avatar', [AuthController::class, 'updateAvatar']);
    Route::get('/orders/files/{fileId}/download', [OrderController::class, 'downloadFile']);
    Route::post('orders/{order}/payment-proof', [OrderController::class, 'uploadPaymentProof']);
    Route::post('auth/logout',  [AuthController::class, 'logout']);
    Route::get('auth/me',       [AuthController::class, 'me']);
    Route::post('auth/refresh', [AuthController::class, 'refresh']);

    // Pedidos — cliente y admin
    Route::get('orders',                    [OrderController::class, 'index']);
    Route::post('orders',                   [OrderController::class, 'store']);
    Route::get('orders/{order}',            [OrderController::class, 'show']);
    Route::patch('orders/{order}/respond',  [OrderController::class, 'respond']);

    // Solo admin
    Route::middleware('role:admin')->group(function () {
        Route::patch('orders/{order}/quote',   [OrderController::class, 'quote']);
        Route::patch('orders/{order}/status',  [OrderController::class, 'updateStatus']);

        Route::post('materials',                    [MaterialController::class, 'store']);
        Route::put('materials/{material}',          [MaterialController::class, 'update']);
        Route::delete('materials/{material}',       [MaterialController::class, 'destroy']);
        Route::post('materials/{material}/colors',  [MaterialController::class, 'addColor']);
        Route::delete('material-colors/{color}',    [MaterialController::class, 'removeColor']);

        Route::post('finishes',            [FinishController::class, 'store']);
        Route::put('finishes/{finish}',    [FinishController::class, 'update']);
        Route::delete('finishes/{finish}', [FinishController::class, 'destroy']);

        Route::get('admin/stats',   [AdminController::class, 'stats']);
        Route::get('admin/clients', [AdminController::class, 'clients']);
    });
});