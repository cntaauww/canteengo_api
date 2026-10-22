
<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Api\MenuController;
use App\Http\Controllers\Api\AuthController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Menu publik: mahasiswa dapat melihat menu tanpa login.
Route::get('/menus', [MenuController::class, 'index']);

// Registrasi mahasiswa dan login.
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Endpoint yang memerlukan autentikasi.
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/profile', [AuthController::class, 'profile']);

    // Pengelolaan menu dengan pemeriksaan role di MenuController.
    Route::post('/menus', [MenuController::class, 'store']);
    Route::put('/menus/{menu}', [MenuController::class, 'update']);
    Route::patch('/menus/{menu}', [MenuController::class, 'update']);
    Route::delete('/menus/{menu}', [MenuController::class, 'destroy']);

    // Dashboard hanya untuk admin aktif.
    Route::get('/admin/dashboard', function (Request $request) {
        $user = $request->user();

        if ($user->status_akun !== 'aktif') {
            return response()->json([
                'success' => false,
                'message' => 'Akun kamu tidak aktif.',
            ], 403);
        }

        if ($user->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya admin yang boleh mengakses dashboard ini.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'message' => 'Ringkasan dashboard berhasil diambil.',
            'data' => [
                'total_pengguna' => DB::table('users')->count(),
                'total_menu' => DB::table('menus')->count(),
            ],
        ]);
    });
});
