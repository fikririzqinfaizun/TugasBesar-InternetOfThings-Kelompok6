<?php

use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

// Endpoint untuk ESP32 & Scan Barang Terjual
Route::post('/inventory/update', [ProductController::class, 'updateInventory']);
Route::post('/inventory/sell', [ProductController::class, 'sellProduct']);

// Endpoint untuk sinkronisasi warna baju (PNK, HJU, MRH, HTM, PTH) & auto-refresh dashboard
Route::post('/inventory/set-color', [ProductController::class, 'setActiveColor']);
Route::get('/inventory/realtime', [ProductController::class, 'getRealtimeData']);