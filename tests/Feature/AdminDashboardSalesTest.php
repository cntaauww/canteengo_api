<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminDashboardSalesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_returns_weekly_and_monthly_confirmed_sales_by_canteen(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'status_akun' => 'aktif',
        ]);
        User::factory()->create([
            'name' => 'PK Satu',
            'role' => 'pk',
            'nomor_kantin' => 1,
            'status_akun' => 'aktif',
        ]);

        $today = CarbonImmutable::today();
        $this->createConfirmedOrder(1, 15000, $today->setTime(12, 0));
        $this->createConfirmedOrder(
            1,
            10000,
            $today->startOfMonth()->subMonth()->addDay()->setTime(12, 0),
        );
        $this->createConfirmedOrder(2, 25000, $today->setTime(13, 0));
        $this->createConfirmedOrder(
            2,
            30000,
            $today->startOfMonth()->subMonth()->addDay()->setTime(13, 0),
        );

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/dashboard')->assertOk();
        $data = $response->json('data');

        $this->assertSame(4, $data['total_pesanan']);
        $this->assertCount(3, $data['grafik_penjualan_mingguan']['series']);
        $this->assertCount(7, $data['grafik_penjualan_mingguan']['series'][0]['data']);
        $this->assertSame(
            'PK Satu',
            $data['grafik_penjualan_mingguan']['series'][0]['nama_pk'],
        );
        $this->assertEquals(
            15000,
            collect($data['grafik_penjualan_mingguan']['series'][0]['data'])
                ->sum('total_penjualan'),
        );
        $this->assertEquals(
            15000,
            $data['perbandingan_penjualan_bulanan']['series'][0]['penjualan_bulan_ini'],
        );
        $this->assertEquals(
            10000,
            $data['perbandingan_penjualan_bulanan']['series'][0]['penjualan_bulan_lalu'],
        );
        $this->assertEquals(
            50,
            $data['perbandingan_penjualan_bulanan']['series'][0]['perubahan_persen'],
        );
        $canteenTwoComparison =
            $data['perbandingan_penjualan_bulanan']['series'][1];
        $this->assertTrue($canteenTwoComparison['menurun']);
        $this->assertLessThan(0, $canteenTwoComparison['perubahan_persen']);
    }

    public function test_non_admin_cannot_access_dashboard_sales(): void
    {
        $student = User::factory()->create([
            'role' => 'mahasiswa',
            'status_akun' => 'aktif',
        ]);

        Sanctum::actingAs($student);

        $this->getJson('/api/admin/dashboard')
            ->assertForbidden()
            ->assertJsonPath('success', false);
    }

    private function createConfirmedOrder(
        int $canteen,
        int $amount,
        CarbonImmutable $confirmedAt,
    ): void {
        $customer = User::factory()->create([
            'role' => 'mahasiswa',
            'status_akun' => 'aktif',
        ]);

        Order::create([
            'kode_pesanan' => 'CG-' . fake()->unique()->numerify('########'),
            'user_id' => $customer->id,
            'nomor_kantin' => $canteen,
            'total_harga' => $amount,
            'status_pesanan' => 'diproses',
            'status_pembayaran' => 'terkonfirmasi',
            'metode_pengambilan' => 'ambil_sendiri',
            'waktu_dikonfirmasi' => $confirmedAt,
            'created_at' => $confirmedAt,
            'updated_at' => $confirmedAt,
        ]);
    }
}
