<?php

use App\Http\Controllers\ProductController;
use App\Http\Controllers\SaleController;
use Illuminate\Support\Facades\Route;

// Halaman utama dashboard Smart Fashion Inventory
Route::get('/', [ProductController::class, 'index'])
    ->name('dashboard');

Route::get('/dashboard', [ProductController::class, 'index']);

// Halaman detail produk melalui QR Code
Route::get('/product/{sku}', [ProductController::class, 'showDetail'])
    ->name('product.detail');

// CRUD Barang Keluar / Penjualan
Route::resource('sales', SaleController::class)
    ->except(['show']);
