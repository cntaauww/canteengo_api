<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminOrderMonitoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_admin_can_view_orders_from_all_canteens(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'status_akun' => 'aktif',
        ]);
        $customer = User::factory()->create([
            'role' => 'mahasiswa',
            'status_akun' => 'aktif',
        ]);

        $order = Order::create([
            'kode_pesanan' => 'CG-ADMIN01',
            'user_id' => $customer->id,
            'nomor_kantin' => 2,
            'total_harga' => 15000,
            'status_pesanan' => 'menunggu_pembayaran',
            'status_pembayaran' => 'belum_dikonfirmasi',
            'metode_pengambilan' => 'ambil_sendiri',
        ]);
        $order->items()->create([
            'nama_menu' => 'Nasi Goreng',
            'jumlah' => 1,
            'harga_satuan' => 15000,
            'subtotal' => 15000,
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/orders')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.kode_pesanan', 'CG-ADMIN01')
            ->assertJsonPath('data.0.nomor_kantin', 2)
            ->assertJsonPath('data.0.user.name', $customer->name)
            ->assertJsonPath('data.0.items.0.nama_menu', 'Nasi Goreng');
    }

    public function test_non_admin_cannot_view_all_orders(): void
    {
        $student = User::factory()->create([
            'role' => 'mahasiswa',
            'status_akun' => 'aktif',
        ]);

        Sanctum::actingAs($student);

        $this->getJson('/api/admin/orders')
            ->assertForbidden()
            ->assertJsonPath('success', false);
    }
}
