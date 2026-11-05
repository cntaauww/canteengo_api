<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminDashboardController extends Controller
{
    public function show(Request $request)
    {
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

        $today = CarbonImmutable::today();
        $weekStart = $today->subDays(6);
        $monthStart = $today->startOfMonth();
        $previousMonthStart = $monthStart->subMonth();
        $previousMonthEnd = $previousMonthStart->day(
            min($today->day, $previousMonthStart->daysInMonth),
        );

        $dailySales = Order::query()
            ->where('status_pembayaran', 'terkonfirmasi')
            ->whereRaw(
                'DATE(COALESCE(waktu_dikonfirmasi, created_at)) BETWEEN ? AND ?',
                [$previousMonthStart->toDateString(), $today->toDateString()],
            )
            ->selectRaw(
                'nomor_kantin, DATE(COALESCE(waktu_dikonfirmasi, created_at)) as tanggal, SUM(total_harga) as total_penjualan',
            )
            ->groupBy('nomor_kantin')
            ->groupByRaw('DATE(COALESCE(waktu_dikonfirmasi, created_at))')
            ->get();

        $salesByCanteenAndDate = [];
        foreach ($dailySales as $sale) {
            $date = CarbonImmutable::parse($sale->tanggal)->toDateString();
            $canteen = (int) $sale->nomor_kantin;
            $salesByCanteenAndDate[$canteen][$date] = (float) $sale->total_penjualan;
        }

        $pkNames = User::query()
            ->where('role', 'pk')
            ->where('status_akun', 'aktif')
            ->whereNotNull('nomor_kantin')
            ->orderBy('id')
            ->get(['name', 'nomor_kantin'])
            ->groupBy('nomor_kantin')
            ->map(fn ($users) => $users->pluck('name')->implode(', '));

        $canteens = collect([1, 2, 3]);
        $weeklyDates = collect(range(0, 6))
            ->map(fn (int $offset) => $weekStart->addDays($offset)->toDateString());

        $weeklySeries = $canteens->map(function (int $canteen) use (
            $pkNames,
            $weeklyDates,
            $salesByCanteenAndDate,
        ) {
            return [
                'nomor_kantin' => $canteen,
                'nama_pk' => $pkNames->get($canteen) ?: 'PK Kantin ' . $canteen,
                'data' => $weeklyDates->map(fn (string $date) => [
                    'tanggal' => $date,
                    'total_penjualan' => $salesByCanteenAndDate[$canteen][$date] ?? 0,
                ])->values(),
            ];
        })->values();

        $monthlySeries = $canteens->map(function (int $canteen) use (
            $pkNames,
            $monthStart,
            $today,
            $previousMonthStart,
            $previousMonthEnd,
            $salesByCanteenAndDate,
        ) {
            $currentSales = 0;
            for ($date = $monthStart; $date <= $today; $date = $date->addDay()) {
                $currentSales += $salesByCanteenAndDate[$canteen][$date->toDateString()] ?? 0;
            }

            $previousSales = 0;
            for (
                $date = $previousMonthStart;
                $date <= $previousMonthEnd;
                $date = $date->addDay()
            ) {
                $previousSales += $salesByCanteenAndDate[$canteen][$date->toDateString()] ?? 0;
            }

            $changePercent = $previousSales > 0
                ? (($currentSales - $previousSales) / $previousSales) * 100
                : null;

            return [
                'nomor_kantin' => $canteen,
                'nama_pk' => $pkNames->get($canteen) ?: 'PK Kantin ' . $canteen,
                'penjualan_bulan_ini' => $currentSales,
                'penjualan_bulan_lalu' => $previousSales,
                'perubahan_persen' => $changePercent,
                'menurun' => $changePercent !== null && $changePercent < 0,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'message' => 'Ringkasan dashboard berhasil diambil.',
            'data' => [
                'total_pengguna' => DB::table('users')->count(),
                'total_kantin' => 3,
                'total_menu' => DB::table('menus')->count(),
                'total_pesanan' => DB::table('orders')->count(),
                'grafik_penjualan_mingguan' => [
                    'mulai' => $weekStart->toDateString(),
                    'sampai' => $today->toDateString(),
                    'series' => $weeklySeries,
                ],
                'perbandingan_penjualan_bulanan' => [
                    'bulan_ini_mulai' => $monthStart->toDateString(),
                    'bulan_ini_sampai' => $today->toDateString(),
                    'bulan_lalu_mulai' => $previousMonthStart->toDateString(),
                    'bulan_lalu_sampai' => $previousMonthEnd->toDateString(),
                    'series' => $monthlySeries,
                ],
            ],
        ]);
    }
}
