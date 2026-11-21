<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Barang Keluar - Smart Fashion Inventory</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-950 text-slate-100">

    <div class="mx-auto max-w-3xl px-6 py-10">

        <!-- Header -->
        <div class="mb-8">
            <a href="{{ route('sales.index') }}"
               class="text-sm font-semibold text-amber-400 hover:text-amber-300">
                &larr; Kembali ke Barang Keluar
            </a>

            <p class="mt-6 text-sm font-semibold uppercase tracking-widest text-amber-400">
                Smart Fashion Inventory
            </p>

            <h1 class="mt-2 text-3xl font-bold">Tambah Barang Keluar</h1>

            <p class="mt-2 text-slate-400">
                Catat transaksi barang keluar dan perbarui stok produk.
            </p>
        </div>

        <!-- Form -->
        <div class="rounded-2xl border border-slate-800 bg-slate-900 p-6 sm:p-8">

            @if ($errors->any())
                <div class="mb-6 rounded-xl border border-red-500/30 bg-red-500/10 p-4 text-red-300">
                    <ul class="list-inside list-disc">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('sales.store') }}" method="POST" class="space-y-6">
                @csrf

                <!-- Pilih Produk -->
                <div>
                    <label for="product_id" class="mb-2 block text-sm font-semibold text-slate-300">
                        Produk <span class="text-red-400">*</span>
                    </label>

                    <select id="product_id"
                            name="product_id"
                            required
                            class="w-full rounded-xl border border-slate-700 bg-slate-800 px-4 py-3 text-slate-100 outline-none focus:border-amber-400">
                        <option value="">-- Pilih Produk --</option>

                        @foreach ($products as $product)
                            <option value="{{ $product->id }}"
                                    data-stock="{{ $product->stok_tersedia }}"
                                    {{ old('product_id') == $product->id ? 'selected' : '' }}>
                                {{ $product->nama_pakaian }}
                                ({{ $product->sku }})
                                - Stok: {{ $product->stok_tersedia }}
                            </option>
                        @endforeach
                    </select>

                    @error('product_id')
                        <p class="mt-2 text-sm text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Informasi Stok -->
                <div class="rounded-xl border border-slate-700 bg-slate-800/70 p-4">
                    <p class="text-sm text-slate-400">Stok tersedia</p>
                    <p id="stockInfo" class="mt-1 text-2xl font-bold text-amber-400">
                        -
                    </p>
                </div>

                <!-- Jumlah -->
                <div>
                    <label for="quantity" class="mb-2 block text-sm font-semibold text-slate-300">
                        Jumlah Barang Keluar <span class="text-red-400">*</span>
                    </label>

                    <input type="number"
                           id="quantity"
                           name="quantity"
                           min="1"
                           value="{{ old('quantity', 1) }}"
                           required
                           class="w-full rounded-xl border border-slate-700 bg-slate-800 px-4 py-3 text-slate-100 outline-none focus:border-amber-400">

                    @error('quantity')
                        <p class="mt-2 text-sm text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Tanggal -->
                <div>
                    <label for="tanggal_penjualan" class="mb-2 block text-sm font-semibold text-slate-300">
                        Tanggal Penjualan <span class="text-red-400">*</span>
                    </label>

                    <input type="datetime-local"
                           id="tanggal_penjualan"
                           name="tanggal_penjualan"
                           value="{{ old('tanggal_penjualan', now()->format('Y-m-d\TH:i')) }}"
                           required
                           class="w-full rounded-xl border border-slate-700 bg-slate-800 px-4 py-3 text-slate-100 outline-none focus:border-amber-400">

                    @error('tanggal_penjualan')
                        <p class="mt-2 text-sm text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Catatan -->
                <div>
                    <label for="catatan" class="mb-2 block text-sm font-semibold text-slate-300">
                        Catatan
                    </label>

                    <textarea id="catatan"
                              name="catatan"
                              rows="4"
                              maxlength="1000"
                              placeholder="Tambahkan catatan jika diperlukan..."
                              class="w-full rounded-xl border border-slate-700 bg-slate-800 px-4 py-3 text-slate-100 outline-none focus:border-amber-400">{{ old('catatan') }}</textarea>

                    @error('catatan')
                        <p class="mt-2 text-sm text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Tombol -->
                <div class="flex flex-col-reverse gap-3 pt-2 sm:flex-row sm:justify-end">
                    <a href="{{ route('sales.index') }}"
                       class="rounded-xl border border-slate-700 px-6 py-3 text-center font-semibold transition hover:bg-slate-800">
                        Batal
                    </a>

                    <button type="submit"
                            class="rounded-xl bg-amber-500 px-6 py-3 font-bold text-slate-950 transition hover:bg-amber-400">
                        Simpan Transaksi
                    </button>
                </div>
            </form>
        </div>

    </div>

    <script>
        const productSelect = document.getElementById('product_id');
        const stockInfo = document.getElementById('stockInfo');
        const quantityInput = document.getElementById('quantity');

        function updateStockInfo() {
            const selectedOption = productSelect.options[productSelect.selectedIndex];

            if (!selectedOption || !selectedOption.value) {
                stockInfo.textContent = '-';
                quantityInput.removeAttribute('max');
                return;
            }

            const stock = Number(selectedOption.dataset.stock);

            stockInfo.textContent = stock + ' unit';
            quantityInput.max = stock;
        }

        productSelect.addEventListener('change', updateStockInfo);

        updateStockInfo();
    </script>

</body>
</html>
