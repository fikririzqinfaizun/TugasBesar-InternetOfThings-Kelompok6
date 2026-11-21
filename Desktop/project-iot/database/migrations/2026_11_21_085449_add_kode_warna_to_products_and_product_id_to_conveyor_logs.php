<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Menambahkan identitas warna pada setiap produk.
        if (!Schema::hasColumn('products', 'kode_warna')) {
            Schema::table('products', function (Blueprint $table) {
                $table->string('kode_warna', 3)->nullable();
            });
        }

        // Mempertahankan produk lama sebagai varian Pink.
        DB::table('products')
            ->where('sku', 'PNK-001')
            ->whereNull('kode_warna')
            ->update(['kode_warna' => 'PNK']);

        // Menambahkan hubungan produk pada riwayat conveyor.
        if (!Schema::hasColumn('conveyor_logs', 'product_id')) {
            Schema::table('conveyor_logs', function (Blueprint $table) {
                $table->foreignId('product_id')
                    ->nullable()
                    ->constrained('products')
                    ->nullOnDelete();
            });
        }

        // Menghubungkan riwayat produksi lama ke PNK-001.
        $pinkProduct = DB::table('products')
            ->where('sku', 'PNK-001')
            ->first();

        if ($pinkProduct) {
            DB::table('conveyor_logs')
                ->whereNull('product_id')
                ->update(['product_id' => $pinkProduct->id]);
        }

        // Indeks unik agar satu kode warna tidak dimiliki
        // oleh lebih dari satu produk.
        // [DIUBAH UNTUK MYSQL]
        $indexExists = collect(DB::select("SHOW INDEXES FROM products WHERE Key_name = 'products_kode_warna_unique'"))->isNotEmpty();

        if (!$indexExists) {
            Schema::table('products', function (Blueprint $table) {
                $table->unique('kode_warna', 'products_kode_warna_unique');
            });
        }
    }

    public function down(): void
    {
        // Menghapus indeks unik dan kolom tambahan.
        if (Schema::hasColumn('products', 'kode_warna')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropUnique('products_kode_warna_unique');
                $table->dropColumn('kode_warna');
            });
        }

        if (Schema::hasColumn('conveyor_logs', 'product_id')) {
            Schema::table('conveyor_logs', function (Blueprint $table) {
                $table->dropConstrainedForeignId('product_id');
            });
        }
    }
};