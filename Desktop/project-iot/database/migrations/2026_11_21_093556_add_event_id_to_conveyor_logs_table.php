<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menambahkan identitas unik untuk setiap kejadian produksi ESP32.
     */
    public function up(): void
    {
        Schema::table('conveyor_logs', function (Blueprint $table) {
            $table->string('event_id', 100)->nullable();
            $table->unique('event_id', 'conveyor_logs_event_id_unique');
        });
    }

    /**
     * Menghapus kolom event_id jika migration di-rollback.
     */
    public function down(): void
    {
        Schema::table('conveyor_logs', function (Blueprint $table) {
            $table->dropUnique('conveyor_logs_event_id_unique');
            $table->dropColumn('event_id');
        });
    }
};
