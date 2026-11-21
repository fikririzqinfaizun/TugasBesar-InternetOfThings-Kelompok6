<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaleController extends Controller
{
    /**
     * Menampilkan daftar barang keluar.
     */
    public function index()
    {
        $sales = Sale::with('product')
            ->latest('tanggal_penjualan')
            ->paginate(10);

        $totalTransaksi = Sale::count();
        $totalBarangKeluar = Sale::sum('quantity');

        return view('sales.index', compact(
            'sales',
            'totalTransaksi',
            'totalBarangKeluar'
        ));
    }

    /**
     * Menampilkan formulir tambah penjualan.
     */
    public function create()
    {
        $products = Product::orderBy('nama_pakaian')->get();

        return view('sales.create', compact('products'));
    }

    /**
     * Menyimpan transaksi penjualan baru.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'tanggal_penjualan' => ['required', 'date'],
            'catatan' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($validated) {
            $product = Product::whereKey($validated['product_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if ($product->stok_tersedia < $validated['quantity']) {
                throw ValidationException::withMessages([
                    'quantity' => 'Stok tidak mencukupi. Stok tersedia: '
                        . $product->stok_tersedia,
                ]);
            }

            $product->decrement(
                'stok_tersedia',
                $validated['quantity']
            );

            $product->increment(
                'total_terjual',
                $validated['quantity']
            );

            Sale::create($validated);
        });

        return redirect()
            ->route('sales.index')
            ->with('success', 'Transaksi penjualan berhasil ditambahkan.');
    }

    /**
     * Menampilkan formulir edit transaksi.
     */
    public function edit(Sale $sale)
    {
        $products = Product::orderBy('nama_pakaian')->get();

        return view('sales.edit', compact('sale', 'products'));
    }

    /**
     * Memperbarui transaksi dan menyesuaikan stok.
     */
    public function update(Request $request, Sale $sale)
    {
        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'tanggal_penjualan' => ['required', 'date'],
            'catatan' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($validated, $sale) {
            $lockedSale = Sale::whereKey($sale->id)
                ->lockForUpdate()
                ->firstOrFail();

            $oldProduct = Product::whereKey($lockedSale->product_id)
                ->lockForUpdate()
                ->firstOrFail();

            // Jika produk tetap sama, kembalikan stok transaksi lama
            // terlebih dahulu sebelum menghitung transaksi baru.
            if ((int) $oldProduct->id === (int) $validated['product_id']) {
                $availableStock =
                    (int) $oldProduct->stok_tersedia
                    + (int) $lockedSale->quantity;

                if ($availableStock < (int) $validated['quantity']) {
                    throw ValidationException::withMessages([
                        'quantity' => 'Stok tidak mencukupi. Stok yang dapat digunakan: '
                            . $availableStock,
                    ]);
                }

                $oldProduct->stok_tersedia =
                    $availableStock - (int) $validated['quantity'];

                $oldProduct->total_terjual = max(
                    0,
                    (int) $oldProduct->total_terjual
                    - (int) $lockedSale->quantity
                    + (int) $validated['quantity']
                );

                $oldProduct->save();
            } else {
                // Kembalikan stok ke produk sebelumnya.
                $oldProduct->increment(
                    'stok_tersedia',
                    $lockedSale->quantity
                );

                $oldProduct->total_terjual = max(
                    0,
                    (int) $oldProduct->total_terjual
                    - (int) $lockedSale->quantity
                );

                $oldProduct->save();

                // Ambil dan periksa produk tujuan.
                $newProduct = Product::whereKey($validated['product_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($newProduct->stok_tersedia < $validated['quantity']) {
                    throw ValidationException::withMessages([
                        'quantity' => 'Stok produk tujuan tidak mencukupi. Stok tersedia: '
                            . $newProduct->stok_tersedia,
                    ]);
                }

                $newProduct->decrement(
                    'stok_tersedia',
                    $validated['quantity']
                );

                $newProduct->increment(
                    'total_terjual',
                    $validated['quantity']
                );
            }

            $lockedSale->update($validated);
        });

        return redirect()
            ->route('sales.index')
            ->with('success', 'Transaksi penjualan berhasil diperbarui.');
    }

    /**
     * Menghapus transaksi dan mengembalikan stok.
     */
    public function destroy(Sale $sale)
    {
        DB::transaction(function () use ($sale) {
            $lockedSale = Sale::whereKey($sale->id)
                ->lockForUpdate()
                ->firstOrFail();

            $product = Product::whereKey($lockedSale->product_id)
                ->lockForUpdate()
                ->firstOrFail();

            $product->increment(
                'stok_tersedia',
                $lockedSale->quantity
            );

            $product->total_terjual = max(
                0,
                (int) $product->total_terjual
                - (int) $lockedSale->quantity
            );

            $product->save();

            $lockedSale->delete();
        });

        return redirect()
            ->route('sales.index')
            ->with('success', 'Transaksi dihapus dan stok berhasil dikembalikan.');
    }
}
