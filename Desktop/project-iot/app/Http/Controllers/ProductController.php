<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Rak;
use App\Models\ConveyorLog;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    /**
     * Daftar kode warna produk yang tersedia.
     */
    private const WARNA_VALID = [
        'PNK',
        'HJU',
        'MRH',
        'HTM',
        'PTH',
    ];

    /**
     * Mengambil warna aktif dari cache.
     */
    private function getActiveColor(): string
    {
        $color = Cache::get('active_shirt_color', 'PNK');

        if (!in_array($color, self::WARNA_VALID, true)) {
            return 'PNK';
        }

        return $color;
    }

    /**
     * Mengambil produk berdasarkan warna aktif.
     */
    private function getActiveProduct(): ?Product
    {
        $color = $this->getActiveColor();

        return Product::where('kode_warna', $color)->first()
            ?? Product::where('kode_warna', 'PNK')->first()
            ?? Product::first();
    }

    /**
     * Mengambil rak yang aktif saja.
     */
    private function getActiveRacks()
    {
        return Rak::where('is_active', true)
            ->orderBy('kode_rak')
            ->get();
    }

    /**
     * Dashboard utama Smart Fashion Inventory.
     */
    public function index()
    {
        $products = Product::orderBy('id')->get();

        $product = $this->getActiveProduct();

        abort_if(!$product, 404, 'Data produk belum tersedia.');

        $activeColor = $product->kode_warna ?? 'PNK';

        $racks = $this->getActiveRacks();

        $logs = ConveyorLog::with(['product', 'rak'])
            ->latest()
            ->limit(20)
            ->get();

        return view('dashboard', compact(
            'product',
            'products',
            'racks',
            'logs',
            'activeColor'
        ));
    }

    /**
     * Menampilkan detail produk berdasarkan SKU.
     */
    public function showDetail(string $sku)
    {
        $product = Product::where('sku', $sku)->firstOrFail();

        $latestLog = ConveyorLog::with('rak')
            ->where('product_id', $product->id)
            ->latest()
            ->first();

        return view('product-detail', compact(
            'product',
            'latestLog'
        ));
    }

    /**
     * Mencatat satu produk yang masuk dari conveyor.
     * Mendukung event_id untuk mencegah pencatatan produksi ganda
     * dan sinkron dengan rak_code dari ESP32 (RAK-A, RAK-B, RAK-C).
     */
    public function updateInventory(Request $request)
    {
        $validated = $request->validate([
            'kode_warna'    => 'sometimes|string|in:PNK,HJU,MRH,HTM,PTH',
            'color'         => 'sometimes|string|in:PNK,HJU,MRH,HTM,PTH',
            'sku'           => 'sometimes|string|max:100',
            'kecepatan_rpm' => 'sometimes|nullable|numeric|min:0',
            'speed_rpm'     => 'sometimes|nullable|numeric|min:0',
            'rpm'           => 'sometimes|nullable|numeric|min:0',
            'total_count'   => 'sometimes|integer|min:0',
            'slot'          => 'sometimes|integer|min:0',
            'rak_code'      => 'sometimes|nullable|string|max:20',
            'event_id'      => 'sometimes|nullable|string|max:100',
        ]);

        $color = $validated['kode_warna']
            ?? $validated['color']
            ?? $this->getActiveColor();

        $speed = $validated['kecepatan_rpm']
            ?? $validated['speed_rpm']
            ?? $validated['rpm']
            ?? 50.0;

        $eventId = $validated['event_id'] ?? null;
        $requestedRakCode = $validated['rak_code'] ?? null;

        if (
            isset($validated['kode_warna'])
            || isset($validated['color'])
        ) {
            Cache::forever('active_shirt_color', $color);
        }

        try {
            $result = DB::transaction(function () use (
                $validated,
                $color,
                $speed,
                $eventId,
                $requestedRakCode
            ) {
                /*
                 * Periksa apakah kejadian produksi sudah tercatat.
                 */
                if ($eventId !== null) {
                    $existingLog = ConveyorLog::where(
                        'event_id',
                        $eventId
                    )->first();

                    if ($existingLog) {
                        return [
                            'duplicate' => true,
                            'log' => $existingLog->load([
                                'product',
                                'rak',
                            ]),
                        ];
                    }
                }

                $productQuery = Product::query();

                if (!empty($validated['sku'])) {
                    $productQuery->where('sku', $validated['sku']);
                } else {
                    $productQuery->where('kode_warna', $color);
                }

                $product = $productQuery->lockForUpdate()->first();

                if (!$product) {
                    return [
                        'error' => 'Produk dengan identitas tersebut tidak ditemukan.',
                        'code' => 404,
                    ];
                }

                /*
                 * Prioritaskan rak_code yang dikirim oleh ESP32 (RAK-A / RAK-B / RAK-C)
                 * agar urutan 1-15 RAK-A, 16-30 RAK-B, 31-45 RAK-C sama persis dengan LED fisik.
                 */
                $rack = null;

                if (!empty($requestedRakCode)) {
                    $rack = Rak::where('kode_rak', $requestedRakCode)
                        ->where('is_active', true)
                        ->lockForUpdate()
                        ->first();
                }

                if (!$rack) {
                    $rack = Rak::where('is_active', true)
                        ->whereColumn(
                            'jumlah_terisi',
                            '<',
                            'kapasitas_maksimal'
                        )
                        ->orderBy('kode_rak')
                        ->lockForUpdate()
                        ->first();
                }

                if (!$rack) {
                    return [
                        'error' => 'Semua rak aktif penuh atau data rak belum tersedia.',
                        'code' => 409,
                    ];
                }

                /*
                 * Siapkan data log produksi.
                 */
                $logData = [
                    'product_id' => $product->id,
                    'rak_id' => $rack->id,
                    'kecepatan_rpm' => $speed,
                ];

                if ($eventId !== null) {
                    $logData['event_id'] = $eventId;
                }

                /*
                 * Simpan log di dalam transaksi.
                 */
                $log = ConveyorLog::create($logData);

                // Tambahkan satu unit produksi dan satu unit stok.
                $product->increment('total_produksi');
                $product->increment('stok_tersedia');

                // Tambahkan satu unit ke kapasitas rak (maksimal 15 per rak).
                if ($rack->jumlah_terisi < ($rack->kapasitas_maksimal ?? 15)) {
                    $rack->increment('jumlah_terisi');
                }

                $product->refresh();
                $rack->refresh();

                return [
                    'duplicate' => false,
                    'product' => $product,
                    'rack' => $rack,
                    'log' => $log->load([
                        'product',
                        'rak',
                    ]),
                ];
            });
        } catch (QueryException $exception) {
            /*
             * Tangani permintaan bersamaan dengan event_id yang sama.
             */
            if ($eventId !== null) {
                $existingLog = ConveyorLog::with([
                    'product',
                    'rak',
                ])->where('event_id', $eventId)->first();

                if ($existingLog) {
                    return response()->json([
                        'status' => 'success',
                        'message' => 'Kejadian produksi sudah pernah dicatat.',
                        'duplicate' => true,
                        'product' => $existingLog->product,
                        'selected_product' => $existingLog->product,
                        'rack' => $existingLog->rak,
                        'log' => $existingLog,
                    ]);
                }
            }

            throw $exception;
        }

        if (isset($result['error'])) {
            return response()->json([
                'status' => 'error',
                'message' => $result['error'],
            ], $result['code']);
        }

        /*
         * Jika event_id sudah pernah diproses,
         * kembalikan hasil sebelumnya tanpa menambah stok.
         */
        if ($result['duplicate'] === true) {
            return response()->json([
                'status' => 'success',
                'message' => 'Kejadian produksi sudah pernah dicatat.',
                'duplicate' => true,
                'product' => $result['log']->product,
                'selected_product' => $result['log']->product,
                'rack' => $result['log']->rak,
                'log' => $result['log'],
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Produksi berhasil dicatat.',
            'duplicate' => false,
            'product' => $result['product'],
            'selected_product' => $result['product'],
            'rack' => $result['rack'],
            'log' => $result['log'],
        ]);
    }

    /**
     * Mencatat transaksi penjualan produk dan mengurangi isi rak yang terisi.
     */
    public function sellProduct(Request $request)
    {
        $validated = $request->validate([
            'sku' => 'sometimes|string|max:100',
            'kode_warna' => 'sometimes|string|in:PNK,HJU,MRH,HTM,PTH',
            'product_id' => 'sometimes|integer',
        ]);

        $result = DB::transaction(function () use ($validated) {
            $query = Product::query();

            if (!empty($validated['product_id'])) {
                $query->where('id', $validated['product_id']);
            } elseif (!empty($validated['sku'])) {
                $query->where('sku', $validated['sku']);
            } else {
                $color = $validated['kode_warna']
                    ?? $this->getActiveColor();

                $query->where('kode_warna', $color);
            }

            $product = $query->lockForUpdate()->first();

            if (!$product) {
                return [
                    'error' => 'Produk tidak ditemukan.',
                    'code' => 404,
                ];
            }

            if ($product->stok_tersedia <= 0) {
                return [
                    'error' => 'Stok produk tidak mencukupi.',
                    'code' => 422,
                ];
            }

            $product->decrement('stok_tersedia');
            $product->increment('total_terjual');

            // Kurangi 1 isi rak dari rak terakhir yang memiliki stok terisi
            $lastFilledRack = Rak::where('is_active', true)
                ->where('jumlah_terisi', '>', 0)
                ->orderByDesc('kode_rak')
                ->lockForUpdate()
                ->first();

            if ($lastFilledRack) {
                $lastFilledRack->decrement('jumlah_terisi');
            }

            $product->refresh();

            return [
                'product' => $product,
            ];
        });

        if (isset($result['error'])) {
            return response()->json([
                'status' => 'error',
                'message' => $result['error'],
            ], $result['code']);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Penjualan berhasil dicatat.',
            'product' => $result['product'],
        ]);
    }

    /**
     * Mengubah warna produk yang sedang dipilih (menetap sampai diubah lagi).
     */
    public function setActiveColor(Request $request)
    {
        $validated = $request->validate([
            'kode_warna' => 'required|string|in:PNK,HJU,MRH,HTM,PTH',
        ]);

        $color = $validated['kode_warna'];

        $product = Product::where('kode_warna', $color)->first();

        if (!$product) {
            return response()->json([
                'status' => 'error',
                'message' => 'Produk dengan warna tersebut tidak ditemukan.',
            ], 404);
        }

        Cache::forever('active_shirt_color', $color);

        return response()->json([
            'status' => 'success',
            'message' => 'Warna produk berhasil diubah.',
            'active_color' => $color,
            'product' => $product,
            'selected_product' => $product,
        ]);
    }

    /**
     * Mengirim data terbaru untuk dashboard real-time.
     */
    public function getRealtimeData()
    {
        $products = Product::orderBy('id')->get();

        $product = $this->getActiveProduct();

        if (!$product) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data produk belum tersedia.',
            ], 404);
        }

        $activeColor = $product->kode_warna ?? 'PNK';

        $totalProduksi = (int) $products->sum('total_produksi');
        $totalTerjual  = (int) $products->sum('total_terjual');
        $stokAktifRak  = max(0, $totalProduksi - $totalTerjual);

        $totals = [
            'total_produksi' => $totalProduksi,
            'stok_tersedia'  => max(0, 45 - $stokAktifRak),
            'total_terjual'  => $totalTerjual,
            'stok_aktif_rak' => $stokAktifRak,
        ];

        $racks = $this->getActiveRacks();

        $logs = ConveyorLog::with(['product', 'rak'])
            ->latest()
            ->limit(20)
            ->get();

        return response()->json([
            'status' => 'success',
            'product' => $product,
            'selected_product' => $product,
            'products' => $products,
            'totals' => $totals,
            'racks' => $racks,
            'logs' => $logs,
            'active_color' => $activeColor,
        ]);
    }

    /**
     * Mengosongkan seluruh data simulasi produksi dan mengembalikan kapasitas rak ke 0.
     */
    public function resetInventory()
    {
        ConveyorLog::query()->delete();

        Product::query()->update([
            'total_produksi' => 0,
            'stok_tersedia'  => 0,
            'total_terjual'  => 0,
        ]);

        Rak::query()->update([
            'jumlah_terisi' => 0,
        ]);

        Cache::forever('active_shirt_color', 'PNK');

        return response()->json([
            'status'  => 'success',
            'message' => 'Seluruh data inventori berhasil di-reset ke 0.',
        ]);
    }
}