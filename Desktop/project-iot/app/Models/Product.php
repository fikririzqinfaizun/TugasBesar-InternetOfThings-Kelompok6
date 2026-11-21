<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $guarded = [];

    /**
     * Riwayat produksi produk ini.
     */
    public function conveyorLogs(): HasMany
    {
        return $this->hasMany(ConveyorLog::class, 'product_id');
    }
}
