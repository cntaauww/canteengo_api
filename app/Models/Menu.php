<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Menu extends Model
{
    protected $fillable = [
        'nomor_kantin',
        'nama_menu',
        'deskripsi',
        'harga',
        'foto',
        'stok',
        'tersedia',
    ];

    protected function casts(): array
    {
        return [
            'harga' => 'decimal:2',
            'stok' => 'integer',
            'tersedia' => 'boolean',
        ];
    }
}