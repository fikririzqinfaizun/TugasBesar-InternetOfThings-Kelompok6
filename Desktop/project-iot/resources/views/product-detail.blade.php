<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Produk - {{ $product->sku }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-900 text-slate-100 min-h-screen flex items-center justify-center p-4">
    <div class="max-w-md w-full bg-slate-800 rounded-2xl p-6 border border-slate-700 shadow-xl space-y-6">
        <div class="text-center border-b border-slate-700 pb-4">
            <span class="bg-indigo-500/10 text-indigo-400 text-xs font-semibold px-3 py-1 rounded-full border border-indigo-500/20">
                Verified IoT Item
            </span>
            <h1 class="text-2xl font-bold mt-2 text-white">{{ $product->nama_pakaian }}</h1>
            <p class="text-slate-400 text-sm">{{ $product->kategori }}</p>
        </div>

        <div class="space-y-4">
            <div class="flex justify-between items-center bg-slate-900/50 p-3 rounded-lg border border-slate-700/50">
                <span class="text-slate-400 text-sm">SKU Produk</span>
                <span class="font-mono font-bold text-indigo-400">{{ $product->sku }}</span>
            </div>

            <div class="flex justify-between items-center bg-slate-900/50 p-3 rounded-lg border border-slate-700/50">
                <span class="text-slate-400 text-sm">Status Stok</span>
                @if($product->stok_tersedia > 0)
                    <span class="text-emerald-400 text-sm font-semibold">Tersedia ({{ $product->stok_tersedia }})</span>
                @else
                    <span class="text-rose-400 text-sm font-semibold">Habis / Terjual</span>
                @endif
            </div>

            <div class="flex justify-between items-center bg-slate-900/50 p-3 rounded-lg border border-slate-700/50">
                <span class="text-slate-400 text-sm">Lokasi Rak Terakhir</span>
                <span class="bg-slate-800 text-amber-400 text-xs font-bold px-2.5 py-1 rounded border border-amber-400/20">
                    {{ $latestLog->rak->kode_rak ?? 'RAK-A' }}
                </span>
            </div>
        </div>

        <div class="flex justify-center pt-2">
            <img src="{{ $product->qr_code_path }}" alt="QR Code" class="w-36 h-36 bg-white p-2 rounded-xl shadow-inner">
        </div>

        <div class="text-center pt-2">
            <a href="/" class="text-xs text-slate-500 hover:text-slate-300 transition">← Kembali ke Dashboard Server</a>
        </div>
    </div>
</body>
</html>