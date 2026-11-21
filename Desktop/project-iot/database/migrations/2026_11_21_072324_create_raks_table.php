<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('raks')) {
            Schema::create('raks', function (Blueprint $table) {
                $table->id();
                $table->string('kode_rak')->unique();
                $table->string('nama_rak')->nullable();
                $table->integer('kapasitas')->default(8);
                $table->integer('jumlah_terisi')->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('raks');
    }
};