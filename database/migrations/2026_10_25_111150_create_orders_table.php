
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            $table->string('kode_pesanan')->unique();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->unsignedTinyInteger('nomor_kantin');

            $table->decimal('total_harga', 12, 2);

            $table->string('status_pesanan')
                ->default('menunggu_pembayaran');

            $table->string('status_pembayaran')
                ->default('belum_dikonfirmasi');

            $table->string('metode_pengambilan')
                ->default('ambil_sendiri');

            $table->text('catatan')->nullable();

            $table->timestamp('waktu_pembayaran_dilaporkan')
                ->nullable();

            $table->timestamp('waktu_dikonfirmasi')
                ->nullable();

            $table->timestamps();

            $table->index(['user_id', 'status_pesanan']);
            $table->index(['nomor_kantin', 'status_pesanan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
