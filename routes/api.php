<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AdminDashboardController;
use App\Http\Controllers\Api\MenuController;
use App\Http\Controllers\Api\OrderController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AdminUserController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Menu publik.
Route::get('/menus', [MenuController::class, 'index']);

// Registrasi dan login mahasiswa.
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Endpoint yang memerlukan autentikasi.
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/profile', [AuthController::class, 'profile']);
    
  // Manajemen pengguna khusus admin.
Route::get('/admin/users', [AdminUserController::class, 'index']);
Route::get('/admin/orders', [OrderController::class, 'adminOrders']);

Route::patch('/admin/users/{id}/status', [
    AdminUserController::class,
    'updateStatus',
]);

// Pesanan masuk khusus PK.
Route::get('/pk/orders', [OrderController::class, 'pkOrders']);

    // Pengelolaan menu.
    Route::post('/menus', [MenuController::class, 'store']);
    Route::put('/menus/{menu}', [MenuController::class, 'update']);
    Route::patch('/menus/{menu}', [MenuController::class, 'update']);
    Route::delete('/menus/{menu}', [MenuController::class, 'destroy']);

    // Pesanan mahasiswa.
    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store']);

    // Konfirmasi pembayaran oleh PK atau admin.
    Route::post('/orders/{order}/confirm-payment', [
        OrderController::class,
        'confirmPayment',
    ]);
    Route::post('/orders/{order}/mark-ready', [
        OrderController::class,
        'markReady',
    ]);

    Route::get('/admin/dashboard', [AdminDashboardController::class, 'show']);
});
