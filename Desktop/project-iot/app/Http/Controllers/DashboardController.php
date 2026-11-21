<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Fashion Inventory System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen">

    <!-- Top Navigation -->
    <header class="bg-slate-900 border-b-4 border-amber-100 sticky top-0 z-50 shadow-xl">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-slate-800 flex items-center justify-center font-black text-amber-100 shadow-md border-2 border-amber-100">
                    SF
                </div>
                <div>
                    <h1 class="text-base font-black text-white leading-tight">Smart Fashion Inventory</h1>
                    <p class="text-xs font-bold text-slate-400">Conveyor PID & Sorting System</p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-black bg-emerald-500/10 text-emerald-400 border-2 border-amber-100">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-ping"></span>
                    System Online
                </span>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">

        <!-- Stat Cards Grid -->
        <div id="live-stats" class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Total Produksi -->
            <div class="bg-slate-900 border-4 border-amber-100 rounded-2xl p-6 shadow-lg flex items-center justify-between">
                <div>
                    <p class="text-xs font-black uppercase tracking-wider text-amber-100/80">Total Produksi</p>
                    <h3 class="text-4xl font-black text-white mt-2">{{ $product->total_produksi ?? 0 }}</h3>
                    <p class="text-xs font-bold text-slate-400 mt-1">Item terdeteksi IR Sensor</p>
                </div>
                <div class="p-3.5 bg-slate-800 text-indigo-400 rounded-xl border-2 border-amber-100">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                </div>
            </div>

            <!-- Stok Tersedia -->
            <div class="bg-slate-900 border-4 border-amber-100 rounded-2xl p-6 shadow-lg flex items-center justify-between">
                <div>
                    <p class="text-xs font-black uppercase tracking-wider text-amber-100/80">Stok Tersedia</p>
                    <h3 class="text-4xl font-black text-emerald-400 mt-2">{{ $product->stok_tersedia ?? 0 }}</h3>
                    <p class="text-xs font-bold text-slate-400 mt-1">Siap disalurkan di rak</p>
                </div>
                <div class="p-3.5 bg-slate-800 text-emerald-400 rounded-xl border-2 border-amber-100">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>
                </div>
            </div>

            <!-- Barang Terjual -->
            <div class="bg-slate-900 border-4 border-amber-100 rounded-2xl p-6 shadow-lg flex items-center justify-between">
                <div>
                    <p class="text-xs font-black uppercase tracking-wider text-amber-100/80">Barang Terjual</p>
                    <h3 class="text-4xl font-black text-amber-400 mt-2">{{ $product->total_terjual ?? 0 }}</h3>
                    <p class="text-xs font-bold text-slate-400 mt-1">Keluar dari gudang</p>
                </div>
                <div class="p-3.5 bg-slate-800 text-amber-400 rounded-xl border-2 border-amber-100">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
                </div>
            </div>
        </div>

        <!-- Detail Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

            <!-- QR Code Box -->
            <div class="bg-slate-900 border-4 border-amber-100 rounded-2xl p-6 shadow-lg flex flex-col items-center justify-center text-center">
                <h2 class="text-xs font-black text-amber-100 uppercase tracking-wider mb-3">QR Code Produk Unique</h2>

                <!-- Pilihan Warna Baju -->
                <div class="flex flex-wrap justify-center gap-1.5 mb-4">
                    <button type="button" onclick="setWarnaBaju('PNK', '#F472B6')" class="px-3 py-1 rounded-xl text-[11px] font-black border-2 border-pink-400 text-pink-400 hover:bg-pink-400/10 transition cursor-pointer">
                        Pink (PNK)
                    </button>
                    <button type="button" onclick="setWarnaBaju('HJU', '#39FF14')" style="border-color: #39FF14; color: #39FF14;" class="px-3 py-1 rounded-xl text-[11px] font-black border-2 hover:bg-[#39FF14]/10 transition cursor-pointer">
                        Ijo Neon (HJU)
                    </button>
                    <button type="button" onclick="setWarnaBaju('MRH', '#F87171')" class="px-3 py-1 rounded-xl text-[11px] font-black border-2 border-red-400 text-red-400 hover:bg-red-400/10 transition cursor-pointer">
                        Merah (MRH)
                    </button>
                    <button type="button" onclick="setWarnaBaju('HTM', '#94A3B8')" class="px-3 py-1 rounded-xl text-[11px] font-black border-2 border-slate-400 text-slate-300 hover:bg-slate-400/10 transition cursor-pointer">
                        Hitam (HTM)
                    </button>
                    <button type="button" onclick="setWarnaBaju('PTH', '#F8FAFC')" class="px-3 py-1 rounded-xl text-[11px] font-black border-2 border-white text-white hover:bg-white/10 transition cursor-pointer">
                        Putih (PTH)
                    </button>
                </div>

                <!-- Area Label QR -->
                <div id="area-label-qr" style="border-color: #F472B6;" class="p-4 bg-white rounded-2xl border-4 shadow-inner transition-colors duration-200 flex items-center justify-center">
                    <div id="qrcode-canvas" class="w-44 h-44 flex items-center justify-center"></div>
                </div>

                <!-- Badge SKU -->
                <p id="badge-sku-box" style="border-color: #F472B6;" class="mt-4 text-xs font-mono text-slate-200 bg-slate-950 px-5 py-2 rounded-xl border-2 font-bold transition-colors duration-200">
                    SKU: <span id="sku-text" style="color: #F472B6;" class="font-black">PNK-001</span>
                </p>

                <!-- Tombol Download QR -->
                <div class="mt-4 w-full">
                    <button type="button" onclick="downloadQR()" class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 rounded-xl text-xs font-black bg-sky-400 text-slate-950 hover:bg-sky-300 transition shadow-md cursor-pointer">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                        Download QR
                    </button>
                </div>
            </div>

            <!-- Table Riwayat -->
            <div class="lg:col-span-2 bg-slate-900 border-4 border-amber-100 rounded-2xl p-6 shadow-lg flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-4 pb-3 border-b-4 border-amber-100">
                        <h2 class="text-xs font-black text-slate-200 uppercase tracking-wider">Monitoring Konveyor Real-Time</h2>
                        <span class="text-xs font-black text-amber-100 bg-slate-800 px-3 py-1 rounded-lg border-2 border-amber-100">10 Log Terakhir</span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm text-slate-200">
                            <thead class="text-xs font-black text-amber-100 uppercase bg-slate-950 border-b-4 border-amber-100">
                                <tr>
                                    <th class="py-3.5 px-4 border-r-2 border-amber-100">Waktu Log</th>
                                    <th class="py-3.5 px-4 border-r-2 border-amber-100">Alokasi Rak</th>
                                    <th class="py-3.5 px-4 text-right">Kecepatan PID (RPM)</th>
                                </tr>
                            </thead>
                            <tbody id="live-table" class="divide-y-2 divide-slate-800 font-bold">
                                @forelse($logs as $log)
                                    <tr class="hover:bg-slate-800/80 transition-colors">
                                        <td class="py-3.5 px-4 font-mono text-xs text-slate-300 border-r-2 border-slate-800">
                                            {{ $log->created_at->format('H:i:s - d M Y') }}
                                        </td>
                                        <td class="py-3.5 px-4 border-r-2 border-slate-800">
                                            @php
                                                $rakCode = $log->rak->kode_rak ?? '-';
                                                $badgeColor = match($rakCode) {
                                                    'RAK-A' => 'bg-indigo-500/20 text-indigo-300 border-amber-100',
                                                    'RAK-B' => 'bg-purple-500/20 text-purple-300 border-amber-100',
                                                    'RAK-C' => 'bg-pink-500/20 text-pink-300 border-amber-100',
                                                    default => 'bg-slate-500/20 text-slate-400 border-amber-100',
                                                };
                                            @endphp
                                            <span class="px-3 py-1 rounded-lg text-xs font-black border-2 {{ $badgeColor }}">
                                                {{ $rakCode }}
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

    <!-- Script Pengatur Warna SKU, Download QR & Auto-Sync Data -->
    <script>
        let urutanWarna = {
            PNK: 2, // Awal halaman sudah tampil PNK-001, klik berikutnya jadi PNK-002
            HJU: 1,
            MRH: 1,
            HTM: 1,
            PTH: 1
        };

        let hexSaatIni = '#F472B6';
        let skuAktif = 'PNK-001';

        function renderQRCode(teksSku) {
            const container = document.getElementById('qrcode-canvas');
            container.innerHTML = '';

            if (typeof QRCode !== 'undefined') {
                new QRCode(container, {
                    text: teksSku,
                    width: 176,
                    height: 176,
                    colorDark: "#000000",
                    colorLight: "#ffffff",
                    correctLevel: QRCode.CorrectLevel.H
                });
            } else {
                const img = document.createElement('img');
                img.src = `https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=${encodeURIComponent(teksSku)}`;
                img.className = 'w-44 h-44 object-contain';
                container.appendChild(img);
            }
        }

        window.addEventListener('load', function() {
            renderQRCode(skuAktif);
        });

        function setWarnaBaju(kode, hex) {
            hexSaatIni = hex;

            let nomor = String(urutanWarna[kode]).padStart(3, '0');
            skuAktif = `${kode}-${nomor}`;

            document.getElementById('area-label-qr').style.borderColor = hex;
            document.getElementById('badge-sku-box').style.borderColor = hex;

            const skuText = document.getElementById('sku-text');
            skuText.style.color = hex;
            skuText.innerText = skuAktif;

            renderQRCode(skuAktif);
            urutanWarna[kode]++;
        }

        async function downloadQR() {
            const qrContainer = document.getElementById('qrcode-canvas');
            const sourceCanvas = qrContainer.querySelector('canvas');
            const sourceImg = qrContainer.querySelector('img');

            const finalCanvas = document.createElement('canvas');
            const ctx = finalCanvas.getContext('2d');

            finalCanvas.width = 260;
            finalCanvas.height = 300;

            ctx.fillStyle = '#FFFFFF';
            ctx.fillRect(0, 0, finalCanvas.width, finalCanvas.height);

            ctx.strokeStyle = hexSaatIni;
            ctx.lineWidth = 8;
            ctx.strokeRect(4, 4, finalCanvas.width - 8, finalCanvas.height - 8);

            if (sourceCanvas) {
                ctx.drawImage(sourceCanvas, 42, 24, 176, 176);
            } else if (sourceImg) {
                try {
                    const res = await fetch(sourceImg.src);
                    const blob = await res.blob();
                    const bmp = await createImageBitmap(blob);
                    ctx.drawImage(bmp, 42, 24, 176, 176);
                } catch (e) {
                    ctx.drawImage(sourceImg, 42, 24, 176, 176);
                }
            }

            ctx.fillStyle = '#0F172A';
            ctx.font = 'bold 22px monospace';
            ctx.textAlign = 'center';
            ctx.fillText(skuAktif, finalCanvas.width / 2, 250);

            finalCanvas.toBlob(function(blob) {
                const url = URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = url;
                link.download = `${skuAktif}.png`;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                URL.revokeObjectURL(url);
            }, 'image/png');
        }

        // Auto-Sync Kartu Statistik & Tabel Log setiap 2 detik tanpa me-reset QR Code
        setInterval(async () => {
            try {
                const response = await fetch(window.location.href);
                const html = await response.text();
                const doc = new DOMParser().parseFromString(html, 'text/html');

                const newStats = doc.getElementById('live-stats');
                if (newStats) {
                    document.getElementById('live-stats').innerHTML = newStats.innerHTML;
                }

                const newTable = doc.getElementById('live-table');
                if (newTable) {
                    document.getElementById('live-table').innerHTML = newTable.innerHTML;
                }
            } catch (err) {
                console.warn('Menunggu sinkronisasi server...', err);
            }
        }, 2000);
    </script>

</body>
</html>