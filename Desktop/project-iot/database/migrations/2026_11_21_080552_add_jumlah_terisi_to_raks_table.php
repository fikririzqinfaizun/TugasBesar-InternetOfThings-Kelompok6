<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('raks', 'jumlah_terisi')) {
            Schema::table('raks', function (Blueprint $table) {
                $table->integer('jumlah_terisi')->default(0);
            });
        }

        // Menyesuaikan kapasitas seluruh rak menjadi 8 item.
        DB::table('raks')->update([
            'kapasitas_maksimal' => 8,
        ]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('raks', 'jumlah_terisi')) {
            Schema::table('raks', function (Blueprint $table) {
                $table->dropColumn('jumlah_terisi');
            });
        }

        // Mengembalikan kapasitas default sebelumnya.
        DB::table('raks')->update([
            'kapasitas_maksimal' => 100,
        ]);
    }
};
