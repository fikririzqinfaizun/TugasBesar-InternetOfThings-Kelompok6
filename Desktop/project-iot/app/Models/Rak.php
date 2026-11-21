<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rak extends Model
{
    protected $table = 'raks';

    protected $fillable = [
        'kode_rak',
        'nama_rak',
        'kapasitas_maksimal',
        'jumlah_terisi',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'kapasitas_maksimal' => 'integer',
            'jumlah_terisi' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function conveyorLogs(): HasMany
    {
        return $this->hasMany(ConveyorLog::class, 'rak_id');
    }
}
