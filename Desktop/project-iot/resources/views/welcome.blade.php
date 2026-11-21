
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Fashion Inventory System</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcode-generator/1.4.4/qrcode.min.js"></script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800;900&display=swap" rel="stylesheet">

    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        #qrcode-canvas svg,
        #qrcode-canvas img,
        #qrcode-canvas canvas {
            width: 176px !important;
            height: 176px !important;
            display: block;
        }
    </style>
</head>

<body class="bg-slate-950 text-slate-100 min-h-screen">

    <!-- Navigasi -->
    <header class="bg-slate-900 border-b-4 border-amber-100 sticky top-0 z-50 shadow-xl">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">

            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-slate-800 flex items-center justify-center font-black text-amber-100 shadow-md border-2 border-amber-100">
                    SF
                </div>

                <div>
                    <h1 class="text-base font-black text-white leading-tight">
                        Smart Fashion Inventory
                    </h1>

                    <p class="text-xs font-bold text-slate-400">
                        Conveyor PID (80 RPM) &amp; 8 Items/Rack System
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-black bg-emerald-500/10 text-emerald-400 border-2 border-amber-100">
                    <span id="connection-indicator" class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-ping"></span>
                    <span id="connection-status">Menghubungkan...</span>
                </span>
            </div>

        </div>
    </header>

    <!-- Konten Utama -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">

        <!-- Statistik Inventaris -->
        <div id="live-stats" class="grid grid-cols-1 md:grid-cols-3 gap-6">

            <!-- Total Produksi -->
            <div class="bg-slate-900 border-4 border-amber-100 rounded-2xl p-6 shadow-lg flex items-center justify-between">
                <div>
                    <p class="text-xs font-black uppercase tracking-wider text-amber-100/80">
                        Total Produksi
                    </p>

                    <h3 id="total-produksi" class="text-4xl font-black text-white mt-2">
                        {{ $product->total_produksi ?? 0 }}
                    </h3>

                    <p class="text-xs font-bold text-slate-400 mt-1">
                        Item terdeteksi IR Sensor
                    </p>
                </div>

                <div class="p-3.5 bg-slate-800 text-indigo-400 rounded-xl border-2 border-amber-100">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                    </svg>
                </div>
            </div>

            <!-- Stok Tersedia -->
            <div class="bg-slate-900 border-4 border-amber-100 rounded-2xl p-6 shadow-lg flex items-center justify-between">
                <div>
                    <p class="text-xs font-black uppercase tracking-wider text-amber-100/80">
                        Stok Tersedia
                    </p>

                    <h3 id="stok-tersedia" class="text-4xl font-black text-emerald-400 mt-2">
                        {{ $product->stok_tersedia ?? 0 }}
                    </h3>

                    <p class="text-xs font-bold text-slate-400 mt-1">
                        Kapasitas maksimal 8 item / rak
                    </p>
                </div>

                <div class="p-3.5 bg-slate-800 text-emerald-400 rounded-xl border-2 border-amber-100">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path>
                    </svg>
                </div>
            </div>

            <!-- Barang Terjual -->
            <div class="bg-slate-900 border-4 border-amber-100 rounded-2xl p-6 shadow-lg flex items-center justify-between">
                <div>
                    <p class="text-xs font-black uppercase tracking-wider text-amber-100/80">
                        Barang Terjual
                    </p>

                    <h3 id="total-terjual" class="text-4xl font-black text-amber-400 mt-2">
                        {{ $product->total_terjual ?? 0 }}
                    </h3>

                    <p class="text-xs font-bold text-slate-400 mt-1">
                        Keluar dari gudang
                    </p>
                </div>

                <div class="p-3.5 bg-slate-800 text-amber-400 rounded-xl border-2 border-amber-100">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                    </svg>
                </div>
            </div>

        </div>

        <!-- Monitoring Kapasitas Rak -->
        <section class="bg-slate-900 border-4 border-amber-100 rounded-2xl p-6 shadow-lg">

            <div class="flex items-center justify-between mb-6 pb-3 border-b-4 border-amber-100">
                <h2 class="text-sm font-black text-amber-100 uppercase tracking-wider">
                    Monitoring Kapasitas Rak
                </h2>

                <span class="text-xs font-black text-slate-300">
                    Maksimal 8 Item / Rak
                </span>
            </div>

            <div id="rack-container" class="grid grid-cols-1 md:grid-cols-3 gap-5">

                <div class="bg-slate-950 p-5 rounded-xl border-2 border-slate-700">
                    <p class="font-black text-indigo-300">RAK-A</p>
                    <p class="text-sm text-slate-400 mt-2">Memuat data...</p>
                </div>

                <div class="bg-slate-950 p-5 rounded-xl border-2 border-slate-700">
                    <p class="font-black text-purple-300">RAK-B</p>
                    <p class="text-sm text-slate-400 mt-2">Memuat data...</p>
                </div>

                <div class="bg-slate-950 p-5 rounded-xl border-2 border-slate-700">
                    <p class="font-black text-pink-300">RAK-C</p>
                    <p class="text-sm text-slate-400 mt-2">Memuat data...</p>
                </div>

            </div>
        </section>

        <!-- Detail Dashboard -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

            <!-- QR Code -->
            <div class="bg-slate-900 border-4 border-amber-100 rounded-2xl p-6 shadow-lg flex flex-col items-center justify-center text-center">

                <h2 class="text-xs font-black text-amber-100 uppercase tracking-wider mb-3">
                    QR Code Produk
                </h2>

                <!-- Pilihan Warna -->
                <div class="flex flex-wrap justify-center gap-1.5 mb-4">

                    <button type="button"
                        onclick="setWarnaBaju('PNK')"
                        class="px-3 py-1 rounded-xl text-[11px] font-black border-2 border-pink-400 text-pink-400 hover:bg-pink-400/10 transition">
                        Pink (PNK)
                    </button>

                    <button type="button"
                        onclick="setWarnaBaju('HJU')"
                        style="border-color: #39FF14; color: #39FF14;"
                        class="px-3 py-1 rounded-xl text-[11px] font-black border-2 hover:bg-[#39FF14]/10 transition">
                        Ijo Neon (HJU)
                    </button>

                    <button type="button"
                        onclick="setWarnaBaju('MRH')"
                        class="px-3 py-1 rounded-xl text-[11px] font-black border-2 border-red-400 text-red-400 hover:bg-red-400/10 transition">
                        Merah (MRH)
                    </button>

                    <button type="button"
                        onclick="setWarnaBaju('HTM')"
                        class="px-3 py-1 rounded-xl text-[11px] font-black border-2 border-slate-400 text-slate-300 hover:bg-slate-400/10 transition">
                        Hitam (HTM)
                    </button>

                    <button type="button"
                        onclick="setWarnaBaju('PTH')"
                        class="px-3 py-1 rounded-xl text-[11px] font-black border-2 border-white text-white hover:bg-white/10 transition">
                        Putih (PTH)
                    </button>

                </div>

                <!-- QR Code -->
                <div id="area-label-qr"
                    style="border-color: #F472B6;"
                    class="p-4 bg-white rounded-2xl border-4 shadow-inner transition-colors duration-200 flex items-center justify-center">

                    <div id="qrcode-canvas" class="w-44 h-44 flex items-center justify-center"></div>

                </div>

                <!-- SKU Produk -->
                <p id="badge-sku-box"
                    style="border-color: #F472B6;"
                    class="mt-4 text-xs font-mono text-slate-200 bg-slate-950 px-5 py-2 rounded-xl border-2 font-bold transition-colors duration-200">

                    SKU:
                    <span id="sku-text" style="color: #F472B6;" class="font-black">
                        {{ $product->sku }}
                    </span>

                </p>

                <p id="color-status" class="mt-2 text-xs text-slate-400">
                    Warna aktif: {{ $activeColor }}
                </p>

                <p id="qr-status" class="mt-2 text-xs text-slate-400">
                    QR Code mengarah ke detail produk.
                </p>

                <!-- Download QR -->
                <div class="mt-4 w-full">

                    <button type="button"
                        onclick="downloadQR()"
                        class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 rounded-xl text-xs font-black bg-sky-400 text-slate-950 hover:bg-sky-300 transition shadow-md">

                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                        </svg>

                        Download QR

                    </button>

                </div>

            </div>

            <!-- Riwayat Konveyor -->
            <div class="lg:col-span-2 bg-slate-900 border-4 border-amber-100 rounded-2xl p-6 shadow-lg flex flex-col justify-between">

                <div>

                    <div class="flex items-center justify-between mb-4 pb-3 border-b-4 border-amber-100">

                        <h2 class="text-xs font-black text-slate-200 uppercase tracking-wider">
                            Monitoring Konveyor Real-Time
                        </h2>

                        <span class="text-xs font-black text-amber-100 bg-slate-800 px-3 py-1 rounded-lg border-2 border-amber-100">
                            10 Log Terakhir
                        </span>

                    </div>

                    <div class="overflow-x-auto">

                        <table class="w-full text-left text-sm text-slate-200">

                            <thead class="text-xs font-black text-amber-100 uppercase bg-slate-950 border-b-4 border-amber-100">

                                <tr>
                                    <th class="py-3.5 px-4 border-r-2 border-amber-100">
                                        Waktu Log
                                    </th>

                                    <th class="py-3.5 px-4 border-r-2 border-amber-100">
                                        Alokasi Rak
                                    </th>

                                    <th class="py-3.5 px-4 text-right">
                                        Kecepatan PID (RPM)
                                    </th>
                                </tr>

                            </thead>

                            <tbody id="live-table" class="divide-y-2 divide-slate-800 font-bold">

                                @forelse($logs as $log)

                                    <tr class="hover:bg-slate-800/80 transition-colors">

                                        <td class="py-3.5 px-4 font-mono text-xs text-slate-300 border-r-2 border-slate-800">
                                            {{ $log->created_at->format('H:i:s - d M Y') }}
                                        </td>

                                        <td class="py-3.5 px-4 border-r-2 border-slate-800">
                                            <span class="px-3 py-1 rounded-lg text-xs font-black border-2 border-amber-100
                                                {{ ($log->rak->kode_rak ?? '') === 'RAK-A' ? 'bg-indigo-500/20 text-indigo-300' : '' }}
                                                {{ ($log->rak->kode_rak ?? '') === 'RAK-B' ? 'bg-purple-500/20 text-purple-300' : '' }}
                                                {{ ($log->rak->kode_rak ?? '') === 'RAK-C' ? 'bg-pink-500/20 text-pink-300' : '' }}">
                                                {{ $log->rak->kode_rak ?? '-' }}
                                            </span>
                                        </td>

                                        <td class="py-3.5 px-4 text-right font-mono font-black text-emerald-400">
                                            {{ number_format($log->kecepatan_rpm, 1) }} RPM
                                        </td>

                                    </tr>

                                @empty

                                    <tr>
                                        <td colspan="3" class="py-10 text-center text-slate-500 text-xs font-bold">
                                            Belum ada aktivitas konveyor tercatat.
                                        </td>
                                    </tr>

                                @endforelse

                            </tbody>

                        </table>

                    </div>

                </div>

            </div>

        </div>

    </main>

    <script>
        // Data produk dari Laravel.
        let productSku = @json($product->sku);
        let activeColor = @json($activeColor);

        // Endpoint API Laravel.
        const API = {
            realtime: '/api/inventory/realtime',
            setColor: '/api/inventory/set-color'
        };

        // Daftar warna pakaian.
        const colorMap = {
            PNK: '#F472B6',
            HJU: '#39FF14',
            MRH: '#F87171',
            HTM: '#94A3B8',
            PTH: '#F8FAFC'
        };

        // Warna awal mengikuti data yang tersimpan di server.
        let hexSaatIni = colorMap[activeColor] ?? '#F472B6';

        // URL halaman detail produk.
        let productDetailUrl =
            window.location.origin + '/product/' + encodeURIComponent(productSku);

        /**
         * Menerapkan warna aktif pada elemen dashboard.
         */
        function applyActiveColor(hex) {
            hexSaatIni = hex;

            document.getElementById('area-label-qr').style.borderColor = hex;
            document.getElementById('badge-sku-box').style.borderColor = hex;
            document.getElementById('sku-text').style.color = hex;
        }

        /**
         * Membuat QR Code berisi URL detail produk.
         */
        function renderQRCode() {
            const container = document.getElementById('qrcode-canvas');
            const status = document.getElementById('qr-status');

            container.innerHTML = '';

            if (typeof qrcode === 'undefined') {
                status.textContent = 'Library QR Code belum berhasil dimuat.';
                return;
            }

            try {
                const qr = qrcode(0, 'M');

                qr.addData(productDetailUrl);
                qr.make();

                container.innerHTML = qr.createSvgTag({
                    scalable: true,
                    margin: 0
                });

                status.textContent =
                    'QR Code mengarah ke halaman detail produk.';

            } catch (error) {
                console.error('QR Code error:', error);
                status.textContent = 'Gagal membuat QR Code.';
            }
        }

        /**
         * Mengubah warna pakaian melalui API Laravel.
         */
        async function setWarnaBaju(kode) {
            const status = document.getElementById('color-status');
            const hex = colorMap[kode];

            if (!hex) {
                status.textContent = 'Kode warna tidak valid.';
                return;
            }

            status.textContent = 'Menyimpan pilihan warna...';

            try {
                const response = await fetch(API.setColor, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        kode_warna: kode
                    })
                });

                const result = await response.json();

                if (!response.ok || result.status !== 'success') {
                    throw new Error(
                        result.message || 'Gagal mengubah warna.'
                    );
                }

                if (!result.product || !result.active_color) {
                    throw new Error(
                        'Respons produk dari server tidak lengkap.'
                    );
                }

                // Memperbarui warna dan data produk aktif.
                activeColor = result.active_color;
                productSku = result.product.sku;

                // Memperbarui URL detail produk.
                productDetailUrl =
                    window.location.origin +
                    '/product/' +
                    encodeURIComponent(productSku);

                // Memperbarui tampilan SKU.
                document.getElementById('sku-text').textContent = productSku;

                // Memperbarui statistik produk.
                updateStatistics(result.product);

                // Memperbarui warna dashboard.
                applyActiveColor(hex);

                // Membuat ulang QR Code sesuai produk terpilih.
                renderQRCode();

                status.textContent = 'Warna aktif: ' + activeColor;

            } catch (error) {
                console.error('Kesalahan sinkronisasi warna:', error);

                status.textContent = error.message;
            }
        }

        /**
         * Mengunduh QR Code sebagai gambar PNG.
         */
        function downloadQR() {
            const qrContainer = document.getElementById('qrcode-canvas');
            const svgEl = qrContainer.querySelector('svg');

            if (!svgEl) {
                alert('QR Code belum tersedia.');
                return;
            }

            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');

            canvas.width = 260;
            canvas.height = 300;

            ctx.fillStyle = '#FFFFFF';
            ctx.fillRect(0, 0, canvas.width, canvas.height);

            ctx.strokeStyle = hexSaatIni;
            ctx.lineWidth = 8;
            ctx.strokeRect(4, 4, canvas.width - 8, canvas.height - 8);

            const svgData = new XMLSerializer().serializeToString(svgEl);

            const svgBlob = new Blob(
                [svgData],
                { type: 'image/svg+xml;charset=utf-8' }
            );

            const objectUrl = URL.createObjectURL(svgBlob);
            const image = new Image();

            image.onload = function() {
                ctx.drawImage(image, 42, 24, 176, 176);

                ctx.fillStyle = '#0F172A';
                ctx.font = 'bold 17px monospace';
                ctx.textAlign = 'center';

                ctx.fillText(productSku, canvas.width / 2, 235);

                ctx.font = '10px sans-serif';

                ctx.fillText(
                    'Smart Fashion Inventory',
                    canvas.width / 2,
                    255
                );

                URL.revokeObjectURL(objectUrl);

                canvas.toBlob(function(blob) {
                    if (!blob) {
                        alert('Gagal membuat gambar QR Code.');
                        return;
                    }

                    const downloadUrl = URL.createObjectURL(blob);
                    const link = document.createElement('a');

                    link.href = downloadUrl;
                    link.download = productSku + '.png';

                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);

                    URL.revokeObjectURL(downloadUrl);

                }, 'image/png');
            };

            image.onerror = function() {
                URL.revokeObjectURL(objectUrl);
                alert('Gagal memproses gambar QR Code.');
            };

            image.src = objectUrl;
        }

        /**
         * Memperbarui statistik dashboard.
         */
        function updateStatistics(product) {
            document.getElementById('total-produksi').textContent =
                product.total_produksi ?? 0;

            document.getElementById('stok-tersedia').textContent =
                product.stok_tersedia ?? 0;

            document.getElementById('total-terjual').textContent =
                product.total_terjual ?? 0;
        }

        /**
         * Memperbarui tampilan kapasitas rak.
         */
        function updateRacks(racks) {
            const container = document.getElementById('rack-container');

            const colors = {
                'RAK-A': 'text-indigo-300',
                'RAK-B': 'text-purple-300',
                'RAK-C': 'text-pink-300'
            };

            const orderedCodes = ['RAK-A', 'RAK-B', 'RAK-C'];

            container.innerHTML = '';

            orderedCodes.forEach(function(code) {
                const rack = racks.find(item => item.kode_rak === code);

                const filled = Number(rack?.jumlah_terisi ?? 0);
                const capacity = Number(rack?.kapasitas_maksimal ?? 8);

                const percentage = capacity > 0
                    ? Math.min((filled / capacity) * 100, 100)
                    : 0;

                const card = document.createElement('div');

                card.className =
                    'bg-slate-950 p-5 rounded-xl border-2 border-slate-700';

                const title = document.createElement('p');
                title.className = 'font-black ' + colors[code];
                title.textContent = code;

                const count = document.createElement('p');
                count.className = 'text-2xl font-black text-white mt-3';
                count.textContent = filled + ' / ' + capacity + ' Item';

                const progress = document.createElement('div');
                progress.className =
                    'w-full bg-slate-800 rounded-full h-3 mt-4 overflow-hidden';

                const bar = document.createElement('div');
                bar.className = 'bg-emerald-400 h-3 rounded-full';
                bar.style.width = percentage + '%';

                progress.appendChild(bar);

                const status = document.createElement('p');
                status.className = 'text-xs font-bold text-slate-400 mt-3';

                status.textContent = filled >= capacity
                    ? 'Kapasitas penuh'
                    : 'Slot tersedia: ' + (capacity - filled);

                card.appendChild(title);
                card.appendChild(count);
                card.appendChild(progress);
                card.appendChild(status);

                container.appendChild(card);
            });
        }

        /**
         * Memperbarui tabel riwayat konveyor.
         */
        function updateLogs(logs) {
            const table = document.getElementById('live-table');

            table.innerHTML = '';

            if (!logs || logs.length === 0) {
                const row = document.createElement('tr');
                const cell = document.createElement('td');

                cell.colSpan = 3;
                cell.className =
                    'py-10 text-center text-slate-500 text-xs font-bold';

                cell.textContent =
                    'Belum ada aktivitas konveyor tercatat.';

                row.appendChild(cell);
                table.appendChild(row);

                return;
            }

            logs.forEach(function(log) {
                const row = document.createElement('tr');

                row.className =
                    'hover:bg-slate-800/80 transition-colors';

                const dateCell = document.createElement('td');

                dateCell.className =
                    'py-3.5 px-4 font-mono text-xs text-slate-300 border-r-2 border-slate-800';

                const date = new Date(log.created_at);

                dateCell.textContent = isNaN(date.getTime())
                    ? '-'
                    : date.toLocaleString('id-ID');

                const rackCell = document.createElement('td');

                rackCell.className =
                    'py-3.5 px-4 border-r-2 border-slate-800';

                const rackBadge = document.createElement('span');
                const rackCode = log.rak?.kode_rak ?? '-';

                const rackColors = {
                    'RAK-A': 'bg-indigo-500/20 text-indigo-300',
                    'RAK-B': 'bg-purple-500/20 text-purple-300',
                    'RAK-C': 'bg-pink-500/20 text-pink-300'
                };

                rackBadge.className =
                    'px-3 py-1 rounded-lg text-xs font-black border-2 border-amber-100 ' +
                    (rackColors[rackCode] ?? 'bg-slate-500/20 text-slate-400');

                rackBadge.textContent = rackCode;

                rackCell.appendChild(rackBadge);

                const speedCell = document.createElement('td');

                speedCell.className =
                    'py-3.5 px-4 text-right font-mono font-black text-emerald-400';

                speedCell.textContent =
                    Number(log.kecepatan_rpm ?? 0).toFixed(1) + ' RPM';

                row.appendChild(dateCell);
                row.appendChild(rackCell);
                row.appendChild(speedCell);

                table.appendChild(row);
            });
        }

        /**
         * Mengambil data realtime dari Laravel.
         */
        async function refreshDashboard() {
            try {
                const response = await fetch(API.realtime, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json'
                    },
                    cache: 'no-store'
                });

                if (!response.ok) {
                    throw new Error('Gagal mengambil data dashboard.');
                }

                const data = await response.json();

                if (data.status !== 'success' || !data.product) {
                    throw new Error('Format data dashboard tidak sesuai.');
                }

                updateStatistics(data.product);
                updateRacks(data.racks ?? []);
                updateLogs(data.logs ?? []);

                activeColor = data.active_color ?? activeColor;

                // Menyesuaikan SKU dengan produk aktif dari server.
                if (data.product.sku && data.product.sku !== productSku) {
                    productSku = data.product.sku;

                    productDetailUrl =
                        window.location.origin +
                        '/product/' +
                        encodeURIComponent(productSku);

                    document.getElementById('sku-text').textContent =
                        productSku;

                    renderQRCode();
                }

                const currentHex =
                    colorMap[activeColor] ?? '#F472B6';

                applyActiveColor(currentHex);

                document.getElementById('color-status').textContent =
                    'Warna aktif: ' + activeColor;

                document.getElementById('connection-status').textContent =
                    'System Online';

                document.getElementById('connection-indicator').className =
                    'w-2.5 h-2.5 rounded-full bg-emerald-400 animate-ping';

            } catch (error) {
                console.error('Realtime dashboard error:', error);

                document.getElementById('connection-status').textContent =
                    'Connection Error';

                document.getElementById('connection-indicator').className =
                    'w-2.5 h-2.5 rounded-full bg-red-500';
            }
        }

        // Inisialisasi dashboard.
        document.addEventListener('DOMContentLoaded', function() {
            applyActiveColor(hexSaatIni);

            renderQRCode();

            refreshDashboard();

            // Memperbarui data setiap 2 detik.
            setInterval(refreshDashboard, 2000);
        });
    </script>

</body>
</html>
