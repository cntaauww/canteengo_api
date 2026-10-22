<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
{
    Schema::create('menus', function (Blueprint $table) {
        $table->id();
        $table->unsignedTinyInteger('nomor_kantin');
        $table->string('nama_menu');
        $table->text('deskripsi')->nullable();
        $table->decimal('harga', 12, 2);
        $table->string('foto')->nullable();
        $table->integer('stok')->default(0);
        $table->boolean('tersedia')->default(true);
        $table->timestamps();
   });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('menus');
    }
};
