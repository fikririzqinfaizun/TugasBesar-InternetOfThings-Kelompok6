<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barang Keluar - Smart Fashion Inventory</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-950 text-slate-100">

    <div class="mx-auto max-w-7xl px-6 py-10">

        <!-- Header -->
        <div class="mb-8 flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
            <div>
                <p class="text-sm font-semibold uppercase tracking-widest text-amber-400">
                    Smart Fashion Inventory
                </p>
                <h1 class="mt-2 text-3xl font-bold">Barang Keluar</h1>
                <p class="mt-2 text-slate-400">
                    Kelola dan pantau riwayat transaksi barang keluar.
                </p>
            </div>

            <div class="flex flex-wrap gap-3">
                <a href="{{ route('dashboard') }}"
                   class="rounded-lg border border-slate-700 px-5 py-3 font-semibold transition hover:bg-slate-800">
                    Kembali ke Dashboard
                </a>

                <a href="{{ route('sales.create') }}"
                   class="rounded-lg bg-amber-500 px-5 py-3 font-bold text-slate-950 transition hover:bg-amber-400">
                    + Tambah Barang Keluar
                </a>
            </div>
        </div>

        <!-- Notifikasi -->
        @if (session('success'))
            <div class="mb-6 rounded-xl border border-green-500/30 bg-green-500/10 p-4 text-green-300">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-6 rounded-xl border border-red-500/30 bg-red-500/10 p-4 text-red-300">
                <ul class="list-inside list-disc">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- Ringkasan -->
        <div class="mb-8 grid gap-5 sm:grid-cols-2">
            <div class="rounded-2xl border border-slate-800 bg-slate-900 p-6">
                <p class="text-sm text-slate-400">Total Transaksi</p>
                <p class="mt-3 text-3xl font-bold text-amber-400">
                    {{ number_format($totalTransaksi, 0, ',', '.') }}
                </p>
                <p class="mt-2 text-sm text-slate-500">Transaksi tercatat</p>
            </div>

            <div class="rounded-2xl border border-slate-800 bg-slate-900 p-6">
                <p class="text-sm text-slate-400">Total Barang Keluar</p>
                <p class="mt-3 text-3xl font-bold text-amber-400">
                    {{ number_format($totalBarangKeluar, 0, ',', '.') }}
                </p>
                <p class="mt-2 text-sm text-slate-500">Unit barang terjual</p>
            </div>
        </div>

        <!-- Tabel Transaksi -->
        <div class="overflow-hidden rounded-2xl border border-slate-800 bg-slate-900">
            <div class="border-b border-slate-800 px-6 py-5">
                <h2 class="text-lg font-bold">Riwayat Barang Keluar</h2>
                <p class="mt-1 text-sm text-slate-400">
                    Daftar transaksi penjualan yang telah dicatat.
                </p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-800/70 text-xs uppercase text-slate-400">
                        <tr>
                            <th class="px-6 py-4">No.</th>
                            <th class="px-6 py-4">Produk</th>
                            <th class="px-6 py-4">Jumlah</th>
                            <th class="px-6 py-4">Tanggal Penjualan</th>
                            <th class="px-6 py-4">Catatan</th>
                            <th class="px-6 py-4 text-center">Aksi</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-800">
                        @forelse ($sales as $sale)
                            <tr class="transition hover:bg-slate-800/40">
                                <td class="px-6 py-4 text-slate-400">
                                    {{ $sales->firstItem() + $loop->index }}
                                </td>

                                <td class="px-6 py-4">
                                    <p class="font-semibold">
                                        {{ $sale->product->nama_pakaian ?? 'Produk tidak ditemukan' }}
                                    </p>
                                    <p class="mt-1 text-xs text-slate-500">
                                        {{ $sale->product->sku ?? '-' }}
                                    </p>
                                </td>

                                <td class="px-6 py-4 font-semibold text-amber-400">
                                    {{ number_format($sale->quantity, 0, ',', '.') }} unit
                                </td>

                                <td class="px-6 py-4 text-slate-300">
                                    {{ $sale->tanggal_penjualan?->format('d/m/Y H:i') ?? '-' }}
                                </td>

                                <td class="max-w-xs px-6 py-4 text-slate-400">
                                    {{ $sale->catatan ?: '-' }}
                                </td>

                                <td class="px-6 py-4">
                                    <div class="flex justify-center gap-2">
                                        <a href="{{ route('sales.edit', $sale) }}"
                                           class="rounded-lg bg-blue-500/10 px-3 py-2 font-semibold text-blue-400 transition hover:bg-blue-500/20">
                                            Edit
                                        </a>

                                        <form action="{{ route('sales.destroy', $sale) }}"
                                              method="POST"
                                              onsubmit="return confirm('Yakin ingin menghapus transaksi ini? Stok produk akan dikembalikan.');">
                                            @csrf
                                            @method('DELETE')

                                            <button type="submit"
                                                    class="rounded-lg bg-red-500/10 px-3 py-2 font-semibold text-red-400 transition hover:bg-red-500/20">
                                                Hapus
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-14 text-center">
                                    <p class="text-lg font-semibold text-slate-300">
                                        Belum Ada Transaksi
                                    </p>
                                    <p class="mt-2 text-sm text-slate-500">
                                        Data barang keluar akan ditampilkan setelah transaksi ditambahkan.
                                    </p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            @if ($sales->hasPages())
                <div class="border-t border-slate-800 px-6 py-5">
                    {{ $sales->links() }}
                </div>
            @endif
        </div>

        <p class="mt-6 text-center text-xs text-slate-500">
            Smart Fashion Inventory &copy; {{ date('Y') }}
        </p>

    </div>

</body>
</html>
