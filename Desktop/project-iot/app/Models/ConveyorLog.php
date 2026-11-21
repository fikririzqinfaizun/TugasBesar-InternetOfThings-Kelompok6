<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConveyorLog extends Model
{
    protected $fillable = [
        'product_id',
        'rak_id',
        'kecepatan_rpm',
        'event_id',
    ];

    /**
     * Relasi ke produk yang diproduksi.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * Relasi ke rak penyimpanan.
     */
    public function rak(): BelongsTo
    {
        return $this->belongsTo(Rak::class, 'rak_id');
    }
}
