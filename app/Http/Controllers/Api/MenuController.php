<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Menu;
use Illuminate\Http\Request;

class MenuController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'nomor_kantin' => ['nullable', 'integer', 'between:1,3'],
        ]);

        $menus = Menu::query()
            ->where('tersedia', true)
            ->when(
                $request->filled('nomor_kantin'),
                fn ($query) => $query->where(
                    'nomor_kantin',
                    $request->nomor_kantin
                )
            )
            ->orderBy('nama_menu')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Daftar menu berhasil diambil.',
            'data' => $menus,
        ]);
    }

    private function canManageMenu(Request $request, Menu $menu = null)
    {
        $user = $request->user();

        if (!$user || $user->status_akun !== 'aktif') {
            return response()->json([
                'success' => false,
                'message' => 'Akun tidak aktif atau belum login.',
            ], 403);
        }

        if (!in_array($user->role, ['pk', 'admin'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Kamu tidak memiliki izin mengelola menu.',
            ], 403);
        }

        if ($user->role === 'pk') {
            if (
                !$user->nomor_kantin ||
                ($menu && (int) $menu->nomor_kantin !== (int) $user->nomor_kantin)
            ) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kamu hanya boleh mengelola menu kantin sendiri.',
                ], 403);
            }
        }

        return null;
    }

    public function store(Request $request)
    {
        $denied = $this->canManageMenu($request);

        if ($denied) {
            return $denied;
        }

        $user = $request->user();

        $validated = $request->validate([
            'nomor_kantin' => ['required', 'integer', 'between:1,3'],
            'nama_menu' => ['required', 'string', 'max:255'],
            'deskripsi' => ['nullable', 'string'],
            'harga' => ['required', 'numeric', 'min:0'],
            'foto' => ['nullable', 'string', 'max:255'],
            'stok' => ['required', 'integer', 'min:0'],
            'tersedia' => ['sometimes', 'boolean'],
        ]);

        if (
            $user->role === 'pk' &&
            (int) $validated['nomor_kantin'] !== (int) $user->nomor_kantin
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Kamu hanya boleh menambahkan menu ke kantin sendiri.',
            ], 403);
        }

        $menu = Menu::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Menu berhasil ditambahkan.',
            'data' => $menu,
        ], 201);
    }

    public function update(Request $request, Menu $menu)
    {
        $denied = $this->canManageMenu($request, $menu);

        if ($denied) {
            return $denied;
        }

        $validated = $request->validate([
            'nomor_kantin' => ['sometimes', 'integer', 'between:1,3'],
            'nama_menu' => ['sometimes', 'required', 'string', 'max:255'],
            'deskripsi' => ['sometimes', 'nullable', 'string'],
            'harga' => ['sometimes', 'required', 'numeric', 'min:0'],
            'foto' => ['sometimes', 'nullable', 'string', 'max:255'],
            'stok' => ['sometimes', 'required', 'integer', 'min:0'],
            'tersedia' => ['sometimes', 'boolean'],
        ]);

        $user = $request->user();

        if (
            $user->role === 'pk' &&
            isset($validated['nomor_kantin']) &&
            (int) $validated['nomor_kantin'] !== (int) $user->nomor_kantin
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Menu tidak boleh dipindahkan ke kantin lain.',
            ], 403);
        }

        $menu->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Menu berhasil diperbarui.',
            'data' => $menu->fresh(),
        ]);
    }

    public function destroy(Request $request, Menu $menu)
    {
        $denied = $this->canManageMenu($request, $menu);

        if ($denied) {
            return $denied;
        }

        $menu->delete();

        return response()->json([
            'success' => true,
            'message' => 'Menu berhasil dihapus.',
        ]);
    }
}
