<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('conveyor_logs')) {
            Schema::create('conveyor_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('rak_id')->constrained('raks')->cascadeOnDelete();
                $table->double('kecepatan_rpm')->default(80.0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('conveyor_logs');
    }
};