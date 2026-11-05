<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    /**
     * Registrasi mahasiswa.
     * Role dan status ditentukan oleh backend,
     * bukan dari input pengguna.
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|confirmed',
            'nim' => 'required|string|max:50|unique:users,nim',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Data registrasi tidak valid.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => $request->password,
            'nim' => $request->nim,
            'role' => 'mahasiswa',
            'status_akun' => 'aktif',
        ]);

        $token = $user->createToken('canteengo-flutter')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Registrasi berhasil.',
            'data' => [
                'user' => $user,
                'token' => $token,
            ],
        ], 201);
    }

    /**
     * Login pengguna aktif.
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Email atau password belum valid.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (
            !$user ||
            !Auth::validate([
                'email' => $request->email,
                'password' => $request->password,
            ])
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Email atau password salah.',
            ], 401);
        }

        if ($user->status_akun !== 'aktif') {
            return response()->json([
                'success' => false,
                'message' => 'Akun sedang tidak aktif.',
            ], 403);
        }

        $token = $user->createToken('canteengo-flutter')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login berhasil.',
            'data' => [
                'user' => $user,
                'token' => $token,
            ],
        ]);
    }

    /**
     * Logout dengan menghapus token yang sedang digunakan.
     */
    public function logout(Request $request)
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();

        if ($token && method_exists($token, 'delete')) {
            $token->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Logout berhasil.',
        ]);
    }

    /**
     * Mengambil profil pengguna yang sedang login.
     */
    public function profile(Request $request)
    {
        $user = $request->user();

        if (!$user || $user->status_akun !== 'aktif') {
            return response()->json([
                'success' => false,
                'message' => 'Akun tidak aktif atau belum login.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $user,
        ]);
    }
}
