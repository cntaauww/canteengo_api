<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminUserController extends Controller
{
    private function checkAdmin(Request $request)
    {
        $user = $request->user();

        if (!$user || $user->role !== 'admin' || $user->status_akun !== 'aktif') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya admin aktif yang boleh mengakses fitur ini.',
            ], 403);
        }

        return null;
    }

    // Menampilkan seluruh pengguna.
    public function index(Request $request)
    {
        $denied = $this->checkAdmin($request);

        if ($denied) {
            return $denied;
        }

        $users = User::query()
            ->select([
                'id',
                'name',
                'email',
                'nim',
                'role',
                'nomor_kantin',
                'status_akun',
                'created_at',
            ])
            ->whereIn('role', ['mahasiswa', 'pk', 'admin'])
            ->orderBy('role')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Daftar pengguna berhasil diambil.',
            'data' => $users,
        ]);
    }

    // Mengubah status akun pengguna.
    public function updateStatus(Request $request, int $id)
    {
        $denied = $this->checkAdmin($request);

        if ($denied) {
            return $denied;
        }

        $validated = $request->validate([
            'status_akun' => [
                'required',
                Rule::in(['aktif', 'nonaktif']),
            ],
        ]);

        $user = User::findOrFail($id);

        if ($user->id === $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Kamu tidak boleh mengubah status akun sendiri.',
            ], 403);
        }

        if ($user->role === 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Status akun admin tidak dapat diubah melalui fitur ini.',
            ], 403);
        }

        $user->status_akun = $validated['status_akun'];
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Status akun berhasil diperbarui.',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'status_akun' => $user->status_akun,
            ],
        ]);
    }
}
