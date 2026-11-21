<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Sale extends Model
{
    protected $fillable = [
        'product_id',
        'quantity',
        'tanggal_penjualan',
        'catatan',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'tanggal_penjualan' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
