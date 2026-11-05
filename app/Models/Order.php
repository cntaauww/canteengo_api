<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'kode_pesanan',
        'user_id',
        'nomor_kantin',
        'total_harga',
        'status_pesanan',
        'status_pembayaran',
        'metode_pengambilan',
        'catatan',
        'waktu_pembayaran_dilaporkan',
        'waktu_dikonfirmasi',
    ];

    protected function casts(): array
    {
        return [
            'nomor_kantin' => 'integer',
            'total_harga' => 'decimal:2',
            'waktu_pembayaran_dilaporkan' => 'datetime',
            'waktu_dikonfirmasi' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
