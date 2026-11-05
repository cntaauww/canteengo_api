<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Menu;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    /**
     * Mahasiswa membuat pesanan.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        if ($user->status_akun !== 'aktif') {
            return response()->json([
                'success' => false,
                'message' => 'Akun tidak aktif.',
            ], 403);
        }

        if ($user->role !== 'mahasiswa') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya mahasiswa yang dapat membuat pesanan.',
            ], 403);
        }

        $validated = $request->validate([
            'nomor_kantin' => [
                'required',
                'integer',
                Rule::in([1, 2, 3]),
            ],
            'metode_pengambilan' => [
                'required',
                Rule::in(['ambil_sendiri', 'makan_di_tempat']),
            ],
            'catatan' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.menu_id' => [
                'required',
                'integer',
                'distinct',
                'exists:menus,id',
            ],
            'items.*.jumlah' => [
                'required',
                'integer',
                'min:1',
                'max:100',
            ],
        ]);

        $order = DB::transaction(function () use ($validated, $user) {
            $totalHarga = 0;
            $detailPesanan = [];

            foreach ($validated['items'] as $item) {
                $menu = Menu::query()
                    ->whereKey($item['menu_id'])
                    ->lockForUpdate()
                    ->first();

                if (
                    !$menu ||
                    !$menu->tersedia ||
                    (int) $menu->nomor_kantin !== (int) $validated['nomor_kantin']
                ) {
                    throw ValidationException::withMessages([
                        'items' => [
                            'Menu tidak tersedia atau bukan berasal dari kantin yang dipilih.',
                        ],
                    ]);
                }

                if ($menu->stok < $item['jumlah']) {
                    throw ValidationException::withMessages([
                        'items' => [
                            'Stok menu "' . $menu->nama_menu . '" tidak mencukupi.',
                        ],
                    ]);
                }

                $harga = (float) $menu->harga;
                $subtotal = $harga * $item['jumlah'];

                $totalHarga += $subtotal;

                $detailPesanan[] = [
                    'menu' => $menu,
                    'jumlah' => $item['jumlah'],
                    'harga' => $harga,
                    'subtotal' => $subtotal,
                ];
            }

            $order = Order::create([
                'kode_pesanan' => 'CG-' . strtoupper(Str::random(8)),
                'user_id' => $user->id,
                'nomor_kantin' => $validated['nomor_kantin'],
                'total_harga' => $totalHarga,
                'status_pesanan' => 'menunggu_pembayaran',
                'status_pembayaran' => 'belum_dikonfirmasi',
                'metode_pengambilan' => $validated['metode_pengambilan'],
                'catatan' => $validated['catatan'] ?? null,
            ]);

            foreach ($detailPesanan as $detail) {
                $order->items()->create([
                    'menu_id' => $detail['menu']->id,
                    'nama_menu' => $detail['menu']->nama_menu,
                    'jumlah' => $detail['jumlah'],
                    'harga_satuan' => $detail['harga'],
                    'subtotal' => $detail['subtotal'],
                ]);

                $detail['menu']->decrement('stok', $detail['jumlah']);
            }

            return $order;
        });

        return response()->json([
            'success' => true,
            'message' => 'Pesanan berhasil dibuat. Silakan lakukan pembayaran di luar aplikasi.',
            'data' => $order->load('items'),
        ], 201);
    }

    /**
     * Mahasiswa melihat riwayat pesanannya sendiri.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->status_akun !== 'aktif') {
            return response()->json([
                'success' => false,
                'message' => 'Akun tidak aktif.',
            ], 403);
        }

        if ($user->role !== 'mahasiswa') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya mahasiswa yang dapat melihat riwayat pesanan melalui endpoint ini.',
            ], 403);
        }

        $orders = Order::with('items')
            ->where('user_id', $user->id)
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Riwayat pesanan berhasil diambil.',
            'data' => $orders,
        ]);
    }

    /**
     * PK melihat pesanan masuk dari kantinnya sendiri.
     */
    public function pkOrders(Request $request)
    {
        $user = $request->user();

        if ($user->status_akun !== 'aktif') {
            return response()->json([
                'success' => false,
                'message' => 'Akun tidak aktif.',
            ], 403);
        }

        if ($user->role !== 'pk' || $user->nomor_kantin === null) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya PK yang dapat melihat pesanan kantinnya.',
            ], 403);
        }

        $orders = Order::with(['user', 'items'])
            ->where('nomor_kantin', $user->nomor_kantin)
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Pesanan masuk berhasil diambil.',
            'data' => $orders,
        ]);
    }

    /**
     * Admin melihat pesanan dari seluruh kantin.
     */
    public function adminOrders(Request $request)
    {
        $user = $request->user();

        if ($user->status_akun !== 'aktif') {
            return response()->json([
                'success' => false,
                'message' => 'Akun tidak aktif.',
            ], 403);
        }

        if ($user->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya admin yang boleh melihat seluruh pesanan.',
            ], 403);
        }

        $orders = Order::with(['user:id,name,email', 'items'])
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Daftar pesanan berhasil diambil.',
            'data' => $orders,
        ]);
    }

    /**
     * Pengelola kantin atau admin mengonfirmasi pembayaran.
     */
    public function confirmPayment(Request $request, Order $order)
    {
        $user = $request->user();

        if ($user->status_akun !== 'aktif') {
            return response()->json([
                'success' => false,
                'message' => 'Akun tidak aktif.',
            ], 403);
        }

        if (!in_array($user->role, ['pk', 'admin'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya pengelola kantin atau admin yang dapat mengonfirmasi pembayaran.',
            ], 403);
        }

        // PK hanya boleh mengonfirmasi pesanan kantinnya sendiri.
        if (
            $user->role === 'pk' &&
            (
                $user->nomor_kantin === null ||
                (int) $user->nomor_kantin !== (int) $order->nomor_kantin
            )
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Kamu hanya dapat mengonfirmasi pesanan dari kantinmu sendiri.',
            ], 403);
        }

        $awaitingDirectPaymentConfirmation =
            $order->status_pesanan === 'menunggu_pembayaran' &&
            $order->status_pembayaran === 'belum_dikonfirmasi';
        $awaitingLegacyConfirmation =
            $order->status_pesanan === 'menunggu_konfirmasi' &&
            $order->status_pembayaran === 'menunggu_konfirmasi';

        if (!$awaitingDirectPaymentConfirmation && !$awaitingLegacyConfirmation) {
            return response()->json([
                'success' => false,
                'message' => 'Pesanan tidak menunggu konfirmasi pembayaran atau sudah diproses.',
            ], 422);
        }

        $order->update([
            'status_pesanan' => 'diproses',
            'status_pembayaran' => 'terkonfirmasi',
            'waktu_dikonfirmasi' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pembayaran berhasil dikonfirmasi. Pesanan sedang diproses.',
            'data' => $order->fresh()->load('items'),
        ]);
    }

    /**
     * PK menandai pesanan yang sudah dikonfirmasi sebagai siap diambil.
     */
    public function markReady(Request $request, Order $order)
    {
        $user = $request->user();

        if ($user->status_akun !== 'aktif') {
            return response()->json([
                'success' => false,
                'message' => 'Akun tidak aktif.',
            ], 403);
        }

        if (
            $user->role !== 'pk' ||
            $user->nomor_kantin === null ||
            (int) $user->nomor_kantin !== (int) $order->nomor_kantin
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Kamu hanya dapat memperbarui pesanan dari kantinmu sendiri.',
            ], 403);
        }

        if (
            $order->status_pesanan !== 'diproses' ||
            $order->status_pembayaran !== 'terkonfirmasi'
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya pesanan yang sedang diproses yang dapat ditandai siap diambil.',
            ], 422);
        }

        $order->update(['status_pesanan' => 'siap_diambil']);

        return response()->json([
            'success' => true,
            'message' => 'Pesanan ditandai siap diambil.',
            'data' => $order->fresh()->load('items'),
        ]);
    }
}
