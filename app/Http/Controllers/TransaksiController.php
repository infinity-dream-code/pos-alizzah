<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Transaksi;
use App\Models\DetailTransaksi;
use App\Models\Barang;
use App\Models\WaitingBarang;
use App\Services\BatuAlizzahClient;
use App\Services\MalangAlizzahClient;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;

class TransaksiController extends Controller
{
    public function index(Request $request)
    {
        $query = Transaksi::with(['user', 'detail.barang'])->orderBy('id', 'desc');

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('kode_transaksi', 'LIKE', '%' . $request->search . '%')
                    ->orWhereHas('user', function ($u) use ($request) {
                        $u->where('nama', 'LIKE', '%' . $request->search . '%');
                    });
            });
        }

        if ($request->metode) {
            $query->where('metode', $request->metode);
        }

        if ($request->tanggal_dari) {
            $query->whereDate('tanggal', '>=', $request->tanggal_dari);
        }

        if ($request->tanggal_sampai) {
            $query->whereDate('tanggal', '<=', $request->tanggal_sampai);
        }

        $transaksis = $query->paginate(10);
        return view('admin.transaksi.index', compact('transaksis'));
    }


    private function prepareCartSessionData($cart)
    {
        $cartData = [];
        $waitingUsage = [];
        $waitingConfirmed = [];

        foreach ($cart as $item) {
            $barang = Barang::find($item['id']);
            if (!$barang) continue;

            $cartData[] = [
                'id' => $barang->id,
                'kode_barang' => $barang->kode_barang,
                'name' => $barang->nama_barang,
                'price' => $barang->harga_jual,
                'stok' => $barang->stok,
                'diskon' => $barang->diskon->nilai ?? 0,
                'qty' => $item['qty']
            ];

            if (isset($item['waitingUsage']) && !empty($item['waitingUsage'])) {
                $total = 0;
                foreach ($item['waitingUsage'] as $wid => $qty) {
                    $total += (int)$qty;
                }

                $waitingUsage[$barang->id] = [
                    'total' => $total,
                    'perWaiting' => []
                ];

                foreach ($item['waitingUsage'] as $wid => $qty) {
                    $qtyInt = (int)$qty;
                    if ($qtyInt > 0) {
                        $waitingUsage[$barang->id]['perWaiting'][(int)$wid] = $qtyInt;

                        if (!isset($waitingConfirmed[$barang->id])) {
                            $waitingConfirmed[$barang->id] = [];
                        }
                        $waitingConfirmed[$barang->id][(int)$wid] = true;
                    }
                }
            }
        }

        return [
            'cart' => $cartData,
            'waiting_usage' => $waitingUsage,
            'waiting_confirmed' => $waitingConfirmed
        ];
    }

    private function formatErrorMessage($message)
    {
        $parts = explode('|', $message);
        if (count($parts) >= 3 && $parts[0] === "ERR") {
            $saldo = (int)$parts[2];
            if ($saldo <= 0) {
                return "Saldo tidak cukup. Sisa saldo: Rp " . number_format(max(0, $saldo), 0, ',', '.');
            }
            return trim($parts[1]);
        }
        if (stripos($message, 'tidak ditemukan') !== false) return "Kartu tidak terdaftar";
        if (stripos($message, 'blokir') !== false) return "Kartu diblokir";
        if (stripos($message, 'saldo') !== false) return "Saldo tidak mencukupi";
        return $message;
    }

    /** Inquiry saldo kartu RFID via Batu_Alizzah */
    public function inquirySaldo(Request $request)
    {
        $noKartu = trim((string) $request->input('pid', $request->input('nokartu', '')));
        if ($noKartu === '') {
            return response()->json(['ok' => false, 'error' => 'Kartu RFID kosong'], 400);
        }

        try {
            $result = BatuAlizzahClient::make()->inquirySaldo($noKartu);
            if (!$result['ok']) {
                return response()->json([
                    'ok' => false,
                    'error' => $result['error'] ?: 'Kartu tidak terdaftar',
                    'nama' => $result['nama'],
                    'saldo' => $result['saldo'],
                ], 422);
            }

            return response()->json([
                'ok' => true,
                'nama' => $result['nama'],
                'saldo' => $result['saldo'],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 502);
        }
    }

    /**
     * Foto referensi wajah dari DB malang_alizzah_face (siswa aktif ber-foto).
     * Hanya role kasir (middleware). Tidak expose ke publik.
     */
    public function faceRefs()
    {
        try {
            $rows = DB::connection('malang_face')
                ->table('siswa')
                ->select(['id', 'nis', 'nama', 'foto_wajah'])
                ->where('aktif', 1)
                ->whereNotNull('foto_wajah')
                ->whereRaw('CHAR_LENGTH(foto_wajah) > 30')
                ->orderBy('nama')
                ->get();

            $data = $rows->map(function ($row) {
                return [
                    'id' => (string) ($row->id ?? ''),
                    'nis' => (string) ($row->nis ?? ''),
                    'nama' => (string) ($row->nama ?? ''),
                    'fotoWajah' => (string) ($row->foto_wajah ?? ''),
                    'aktif' => true,
                ];
            })->values();

            return response()->json(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => 'Gagal memuat foto referensi.',
            ], 502);
        }
    }

    /** Inquiry saldo FacePay — Malang_Alizzah_ForVPS (NOKARTU = NIS) */
    public function inquirySaldoFace(Request $request)
    {
        $noKartu = preg_replace('/\D/', '', (string) $request->input('nokartu', $request->input('pid', '')));
        if ($noKartu === '' || strlen($noKartu) > 32) {
            return response()->json(['ok' => false, 'error' => 'NIS siswa tidak valid'], 400);
        }

        // Hanya NIS yang terdaftar FacePay lokal (aktif + punya foto) — cegah probing saldo sembarang
        if (!$this->faceSiswaEligible($noKartu)) {
            return response()->json([
                'ok' => false,
                'error' => 'NIS tidak terdaftar FacePay / belum ada foto referensi',
            ], 422);
        }

        try {
            $result = MalangAlizzahClient::make()->inquirySaldo($noKartu);
            if (!$result['ok']) {
                return response()->json([
                    'ok' => false,
                    'error' => $result['error'] ?: 'NIS tidak terdaftar di merchant',
                    'nama' => $result['nama'],
                    'saldo' => $result['saldo'],
                ], 422);
            }

            $token = bin2hex(random_bytes(16));
            $request->session()->put('facepay.challenge', [
                'nokartu' => $noKartu,
                'token' => hash('sha256', $token),
                'saldo' => (int) $result['saldo'],
                'user_id' => Auth::id(),
                'expires_at' => now()->addMinutes(3)->getTimestamp(),
            ]);

            return response()->json([
                'ok' => true,
                'nama' => $result['nama'],
                'saldo' => $result['saldo'],
                'nokartu' => $noKartu,
                'face_token' => $token,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => 'Gagal inquiry saldo merchant'], 502);
        }
    }

    /** NIS aktif + berfoto di DB face */
    private function faceSiswaEligible(string $nis): bool
    {
        try {
            $rows = DB::connection('malang_face')
                ->table('siswa')
                ->select(['nis'])
                ->where('aktif', 1)
                ->whereNotNull('foto_wajah')
                ->whereRaw('CHAR_LENGTH(foto_wajah) > 30')
                ->get();
            foreach ($rows as $row) {
                if (preg_replace('/\D/', '', (string) $row->nis) === $nis) {
                    return true;
                }
            }
        } catch (\Throwable $e) {
            return false;
        }

        return false;
    }

    /**
     * @return array{ok:bool,error:?string,challenge:?array}
     */
    private function consumeFaceChallenge(Request $request, string $pid): array
    {
        $rawToken = (string) $request->input('face_token', '');
        $challenge = $request->session()->pull('facepay.challenge');

        if (!is_array($challenge) || $rawToken === '') {
            return ['ok' => false, 'error' => 'Sesi FacePay tidak valid. Ulangi scan wajah.', 'challenge' => null];
        }
        if ((int) ($challenge['user_id'] ?? 0) !== (int) Auth::id()) {
            return ['ok' => false, 'error' => 'Sesi FacePay tidak cocok dengan kasir login.', 'challenge' => null];
        }
        if ((int) ($challenge['expires_at'] ?? 0) < now()->getTimestamp()) {
            return ['ok' => false, 'error' => 'Sesi FacePay kedaluwarsa. Ulangi scan wajah.', 'challenge' => null];
        }
        if (!hash_equals((string) ($challenge['token'] ?? ''), hash('sha256', $rawToken))) {
            return ['ok' => false, 'error' => 'Token FacePay tidak valid. Ulangi scan wajah.', 'challenge' => null];
        }
        if ((string) ($challenge['nokartu'] ?? '') !== $pid) {
            return ['ok' => false, 'error' => 'NIS tidak cocok dengan sesi FacePay.', 'challenge' => null];
        }

        return ['ok' => true, 'error' => null, 'challenge' => $challenge];
    }

    public function processTunai(Request $request)
    {
        DB::beginTransaction();
        try {
            $cart = json_decode($request->cart, true);
            if (!$cart || count($cart) == 0) {
                return redirect('/kasir')->with('error', 'Keranjang kosong');
            }

            $items = [];
            $total = 0;
            $total_modal = 0;
            $insufficientStock = [];

            foreach ($cart as $c) {
                $barang = Barang::where('id', $c['id'])->lockForUpdate()->first();
                if (!$barang) {
                    DB::rollBack();
                    $sessionData = $this->prepareCartSessionData($cart);
                    return redirect('/kasir')
                        ->with('error', 'Barang tidak ditemukan')
                        ->with('cart_data', $sessionData['cart'])
                        ->with('waiting_usage', $sessionData['waiting_usage'])
                        ->with('waiting_confirmed', $sessionData['waiting_confirmed'])
                        ->with('use_discount', false);
                }

                $qty_needed = (int) $c['qty'];

                $waitingList = WaitingBarang::where('barang_id', $barang->id)
                    ->orderBy('id', 'asc')
                    ->lockForUpdate()
                    ->get();

                $waitingTotal = $waitingList->sum('stok');
                $available_total = $barang->stok + $waitingTotal;

                if ($qty_needed > $available_total) {
                    $insufficientStock[] = [
                        'barang_id' => $barang->id,
                        'nama' => $barang->nama_barang,
                        'requested' => $qty_needed,
                        'available_qty' => $available_total,
                        'stok' => $barang->stok,
                    ];
                    continue;
                }

                $qty_base = min($qty_needed, $barang->stok);
                $waitingUsage = $c['waitingUsage'] ?? [];
                $groupedItems = [];

                $lastWaitingHargaJual = null;
                $lastWaitingHargaBeli = null;

                if ($qty_base > 0) {
                    $key = $barang->harga_jual . '_' . $barang->harga_beli;
                    if (!isset($groupedItems[$key])) {
                        $groupedItems[$key] = [
                            'barang_id' => $barang->id,
                            'nama' => $barang->nama_barang,
                            'qty' => 0,
                            'harga' => $barang->harga_jual,
                            'harga_beli' => $barang->harga_beli,
                            'subtotal' => 0,
                            'profit' => 0,
                        ];
                    }
                    $groupedItems[$key]['qty'] += $qty_base;
                    $barang->stok -= $qty_base;
                    $barang->save();
                }

                foreach ($waitingUsage as $wid => $wqty) {
                    $wqty = (int) $wqty;
                    if ($wqty <= 0) continue;

                    $waiting = $waitingList->firstWhere('id', (int) $wid);
                    if (!$waiting || $waiting->stok < $wqty) {
                        DB::rollBack();
                        $sessionData = $this->prepareCartSessionData($cart);
                        return redirect('/kasir')
                            ->with('error', 'Stok ' . $barang->nama_barang . ' tidak mencukupi')
                            ->with('cart_data', $sessionData['cart'])
                            ->with('waiting_usage', $sessionData['waiting_usage'])
                            ->with('waiting_confirmed', $sessionData['waiting_confirmed'])
                            ->with('use_discount', false);
                    }

                    $key = $waiting->harga_jual . '_' . $waiting->harga_beli;
                    if (!isset($groupedItems[$key])) {
                        $groupedItems[$key] = [
                            'barang_id' => $barang->id,
                            'nama' => $barang->nama_barang,
                            'qty' => 0,
                            'harga' => $waiting->harga_jual,
                            'harga_beli' => $waiting->harga_beli,
                            'subtotal' => 0,
                            'profit' => 0,
                        ];
                    }
                    $groupedItems[$key]['qty'] += $wqty;

                    $lastWaitingHargaJual = $waiting->harga_jual;
                    $lastWaitingHargaBeli = $waiting->harga_beli;

                    $waiting->stok -= $wqty;
                    if ($waiting->stok <= 0) {
                        $waiting->delete();
                    } else {
                        $waiting->save();
                    }
                }

                foreach ($groupedItems as $item) {
                    $item['subtotal'] = $item['harga'] * $item['qty'];
                    $modal = $item['harga_beli'] * $item['qty'];
                    $item['profit'] = $item['subtotal'] - $modal;
                    $items[] = $item;
                    $total += $item['subtotal'];
                    $total_modal += $modal;
                }

                $remainingWaiting = WaitingBarang::where('barang_id', $barang->id)->orderBy('id', 'asc')->lockForUpdate()->get();
                if ($remainingWaiting->count() > 0) {
                    $nextWaiting = $remainingWaiting->first();
                    $barang->harga_beli = $nextWaiting->harga_beli;
                    $barang->harga_jual = $nextWaiting->harga_jual;
                    $barang->stok += $nextWaiting->stok;
                    $barang->save();
                    $nextWaiting->delete();
                } elseif ($lastWaitingHargaJual !== null && $lastWaitingHargaBeli !== null) {
                    $barang->harga_beli = $lastWaitingHargaBeli;
                    $barang->harga_jual = $lastWaitingHargaJual;
                    $barang->save();
                }
            }

            if (count($insufficientStock) > 0) {
                DB::rollBack();

                $errorMsg = '<div style="text-align:left; font-size:14px;">Stok tidak mencukupi untuk:<div style="margin-top:12px;">';
                foreach ($insufficientStock as $item) {
                    $errorMsg .= '<div style="margin-bottom:10px; padding:10px; border:1px solid #fee2e2; background:#fff1f2; border-radius:8px;">';
                    $errorMsg .= '<div style="font-weight:700; color:#991b1b;">' . $item['nama'] . '</div>';
                    $errorMsg .= '<div style="font-size:13px; color:#7f1d1d; margin-top:4px;">';
                    $errorMsg .= 'Diminta: <b>' . $item['requested'] . '</b> pcs<br>';
                    $errorMsg .= 'Stok tersedia: <b>' . $item['available_qty'] . '</b> pcs';
                    $errorMsg .= '</div></div>';
                }
                $errorMsg .= '</div></div>';

                $sessionData = $this->prepareCartSessionData($cart);
                return redirect('/kasir')
                    ->with('error', $errorMsg)
                    ->with('cart_data', $sessionData['cart'])
                    ->with('waiting_usage', $sessionData['waiting_usage'])
                    ->with('waiting_confirmed', $sessionData['waiting_confirmed'])
                    ->with('updated_stocks', $insufficientStock)
                    ->with('use_discount', false);
            }

            $bayar = (int) str_replace(['Rp ', '.'], '', $request->bayar);
            if ($bayar < $total) {
                DB::rollBack();
                $sessionData = $this->prepareCartSessionData($cart);
                return redirect('/kasir')
                    ->with('error', 'Pembayaran kurang')
                    ->with('cart_data', $sessionData['cart'])
                    ->with('waiting_usage', $sessionData['waiting_usage'])
                    ->with('waiting_confirmed', $sessionData['waiting_confirmed'])
                    ->with('use_discount', false);
            }

            $kembalian = $bayar - $total;
            $tanggal = Carbon::now('Asia/Jakarta');
            $kode = 'TRX-' . $tanggal->format('YmdHis');
            $profit_total = $total - $total_modal;

            $transaksi = Transaksi::create([
                'kode_transaksi' => $kode,
                'tanggal' => $tanggal,
                'total' => $total,
                'bayar' => $bayar,
                'kembalian' => $kembalian,
                'metode' => 'tunai',
                'pelanggan_id' => null,
                'diskon' => 0,
                'grand_total' => $total,
                'profit' => $profit_total,
                'user_id' => Auth::id(),
            ]);

            foreach ($items as $i) {
                DetailTransaksi::create([
                    'transaksi_id' => $transaksi->id,
                    'barang_id' => $i['barang_id'],
                    'nama_barang' => $i['nama'],
                    'qty' => $i['qty'],
                    'harga' => $i['harga'],
                    'subtotal' => $i['subtotal'],
                    'profit' => $i['profit'],
                ]);
            }

            DB::commit();
            return redirect('/kasir')->with('success', 'Pembayaran berhasil!');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect('/kasir')->with('error', 'Gagal: ' . $e->getMessage());
        }
    }

    public function processOnline(Request $request)
    {
        DB::beginTransaction();
        try {
            $cart = json_decode($request->items, true);
            $pid = $request->pid;

            if (!$cart || count($cart) == 0) {
                return redirect('/kasir')->with('error', 'Keranjang kosong.');
            }

            $items = [];
            $total = 0;
            $total_diskon = 0;
            $insufficientStock = [];

            foreach ($cart as $c) {
                $barang = Barang::where('id', $c['id'])->lockForUpdate()->first();
                if (!$barang) {
                    DB::rollBack();
                    $sessionData = $this->prepareCartSessionData($cart);
                    return redirect('/kasir')
                        ->with('error', 'Barang tidak ditemukan!')
                        ->with('cart_data', $sessionData['cart'])
                        ->with('waiting_usage', $sessionData['waiting_usage'])
                        ->with('waiting_confirmed', $sessionData['waiting_confirmed'])
                        ->with('use_discount', true);
                }

                $qty_needed = (int) $c['qty'];

                $waitingList = WaitingBarang::where('barang_id', $barang->id)
                    ->orderBy('id', 'asc')
                    ->lockForUpdate()
                    ->get();

                $waitingTotal = $waitingList->sum('stok');
                $available_total = $barang->stok + $waitingTotal;

                if ($qty_needed > $available_total) {
                    $insufficientStock[] = [
                        'barang_id' => $barang->id,
                        'nama' => $barang->nama_barang,
                        'requested' => $qty_needed,
                        'available_qty' => $available_total,
                        'stok' => $barang->stok,
                    ];
                    continue;
                }

                $qty_base = min($qty_needed, $barang->stok);
                $waitingUsage = $c['waitingUsage'] ?? [];
                $groupedItems = [];

                $lastWaitingHargaJual = null;
                $lastWaitingHargaBeli = null;

                if ($qty_base > 0) {
                    $key = $barang->harga_jual . '_' . $barang->harga_beli;
                    if (!isset($groupedItems[$key])) {
                        $groupedItems[$key] = [
                            'barang_id' => $barang->id,
                            'qty' => 0,
                            'harga' => $barang->harga_jual,
                            'harga_beli' => $barang->harga_beli,
                            'diskon' => $barang->diskon->nilai ?? 0,
                            'diskon_nominal' => 0,
                            'subtotal' => 0,
                            'profit' => 0,
                        ];
                    }
                    $groupedItems[$key]['qty'] += $qty_base;
                    $barang->stok -= $qty_base;
                    $barang->save();
                }

                foreach ($waitingUsage as $wid => $wqty) {
                    $wqty = (int) $wqty;
                    if ($wqty <= 0) continue;

                    $waiting = $waitingList->firstWhere('id', (int) $wid);
                    if (!$waiting || $waiting->stok < $wqty) {
                        DB::rollBack();
                        $sessionData = $this->prepareCartSessionData($cart);
                        return redirect('/kasir')
                            ->with('error', 'Stok ' . $barang->nama_barang . ' tidak mencukupi!')
                            ->with('cart_data', $sessionData['cart'])
                            ->with('waiting_usage', $sessionData['waiting_usage'])
                            ->with('waiting_confirmed', $sessionData['waiting_confirmed'])
                            ->with('use_discount', true);
                    }

                    $key = $waiting->harga_jual . '_' . $waiting->harga_beli;
                    if (!isset($groupedItems[$key])) {
                        $groupedItems[$key] = [
                            'barang_id' => $barang->id,
                            'qty' => 0,
                            'harga' => $waiting->harga_jual,
                            'harga_beli' => $waiting->harga_beli,
                            'diskon' => $barang->diskon->nilai ?? 0,
                            'diskon_nominal' => 0,
                            'subtotal' => 0,
                            'profit' => 0,
                        ];
                    }
                    $groupedItems[$key]['qty'] += $wqty;

                    $lastWaitingHargaJual = $waiting->harga_jual;
                    $lastWaitingHargaBeli = $waiting->harga_beli;

                    $waiting->stok -= $wqty;
                    if ($waiting->stok <= 0) {
                        $waiting->delete();
                    } else {
                        $waiting->save();
                    }
                }

                foreach ($groupedItems as $item) {
                    $subtotal_sebelum = $item['harga'] * $item['qty'];
                    $diskon = floor(($item['diskon'] / 100) * $subtotal_sebelum);
                    $subtotal = $subtotal_sebelum - $diskon;
                    $modal = $item['harga_beli'] * $item['qty'];
                    $profit = $subtotal - $modal;

                    $item['diskon_nominal'] = $diskon;
                    $item['subtotal'] = $subtotal;
                    $item['profit'] = $profit;

                    $items[] = $item;
                    $total += $subtotal_sebelum;
                    $total_diskon += $diskon;
                }

                $remainingWaiting = WaitingBarang::where('barang_id', $barang->id)->orderBy('id', 'asc')->lockForUpdate()->get();
                if ($remainingWaiting->count() > 0) {
                    $nextWaiting = $remainingWaiting->first();
                    $barang->harga_beli = $nextWaiting->harga_beli;
                    $barang->harga_jual = $nextWaiting->harga_jual;
                    $barang->stok += $nextWaiting->stok;
                    $barang->save();
                    $nextWaiting->delete();
                } elseif ($lastWaitingHargaJual !== null && $lastWaitingHargaBeli !== null) {
                    $barang->harga_beli = $lastWaitingHargaBeli;
                    $barang->harga_jual = $lastWaitingHargaJual;
                    $barang->save();
                }
            }

            if (count($insufficientStock) > 0) {
                DB::rollBack();

                $errorMsg = '<div style="text-align:left; font-size:14px;">Stok tidak mencukupi untuk:<div style="margin-top:12px;">';
                foreach ($insufficientStock as $item) {
                    $errorMsg .= '<div style="margin-bottom:10px; padding:10px; border:1px solid #fee2e2; background:#fff1f2; border-radius:8px;">';
                    $errorMsg .= '<div style="font-weight:700; color:#991b1b;">' . $item['nama'] . '</div>';
                    $errorMsg .= '<div style="font-size:13px; color:#7f1d1d; margin-top:4px;">';
                    $errorMsg .= 'Diminta: <b>' . $item['requested'] . '</b> pcs<br>';
                    $errorMsg .= 'Stok tersedia: <b>' . $item['available_qty'] . '</b> pcs';
                    $errorMsg .= '</div></div>';
                }
                $errorMsg .= '</div></div>';

                $sessionData = $this->prepareCartSessionData($cart);
                return redirect('/kasir')
                    ->with('error', $errorMsg)
                    ->with('cart_data', $sessionData['cart'])
                    ->with('waiting_usage', $sessionData['waiting_usage'])
                    ->with('waiting_confirmed', $sessionData['waiting_confirmed'])
                    ->with('updated_stocks', $insufficientStock)
                    ->with('use_discount', true);
            }

            $grand_total = $total - $total_diskon;

            try {
                $pay = BatuAlizzahClient::make()->paymentBelanja((string) $pid, (int) $grand_total, null);
            } catch (\Throwable $e) {
                DB::rollBack();
                $sessionData = $this->prepareCartSessionData($cart);
                return redirect('/kasir')
                    ->with('error', $this->formatErrorMessage($e->getMessage()))
                    ->with('cart_data', $sessionData['cart'])
                    ->with('waiting_usage', $sessionData['waiting_usage'])
                    ->with('waiting_confirmed', $sessionData['waiting_confirmed'])
                    ->with('use_discount', true);
            }

            if (!$pay['ok']) {
                DB::rollBack();
                $sessionData = $this->prepareCartSessionData($cart);
                $errMsg = $pay['message'];
                if ($pay['result'] === 'SALDO_TAK_MENCUKUPI' && $pay['saldo'] !== null) {
                    $errMsg = 'Saldo tidak mencukupi. Sisa saldo: Rp ' . number_format((int) $pay['saldo'], 0, ',', '.');
                }
                return redirect('/kasir')
                    ->with('error', $this->formatErrorMessage($errMsg))
                    ->with('cart_data', $sessionData['cart'])
                    ->with('waiting_usage', $sessionData['waiting_usage'])
                    ->with('waiting_confirmed', $sessionData['waiting_confirmed'])
                    ->with('use_discount', true);
            }

            $return_id = null;

            $tanggal = Carbon::now('Asia/Jakarta');
            $kode = 'TRX-' . $tanggal->format('YmdHis');

            $profit_total = 0;
            foreach ($items as $i) $profit_total += $i['profit'];

            $transaksi = Transaksi::create([
                'kode_transaksi' => $kode,
                'tanggal' => $tanggal,
                'total' => $total,
                'bayar' => $grand_total,
                'kembalian' => 0,
                'metode' => 'online',
                'pelanggan_id' => $pid,
                'diskon_nominal' => $total_diskon,
                'grand_total' => $grand_total,
                'profit' => $profit_total,
                'user_id' => Auth::id(),
            ]);

            foreach ($items as $i) {
                DetailTransaksi::create([
                    'transaksi_id' => $transaksi->id,
                    'barang_id' => $i['barang_id'],
                    'nama_barang' => Barang::find($i['barang_id'])->nama_barang,
                    'qty' => $i['qty'],
                    'harga' => $i['harga'],
                    'diskon' => $i['diskon'],
                    'diskon_nominal' => $i['diskon_nominal'],
                    'subtotal' => $i['subtotal'],
                    'profit' => $i['profit'],
                    'return' => $return_id,
                ]);
            }

            DB::commit();
            $suksesMsg = 'Pembayaran berhasil!';
            if (!empty($pay['nama'])) {
                $suksesMsg .= ' ' . $pay['nama'];
            }
            if ($pay['saldo'] !== null) {
                $suksesMsg .= ' · Sisa saldo: Rp ' . number_format((int) $pay['saldo'], 0, ',', '.');
            }
            return redirect('/kasir')->with('success', $suksesMsg);
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect('/kasir')->with('error', 'Kesalahan sistem: ' . $e->getMessage());
        }
    }

    /**
     * Checkout FacePay — debit Malang_Alizzah PaymentBELANJAKantinWithKeterangan (NOKARTU=NIS).
     */
    public function processFace(Request $request)
    {
        $cart = json_decode($request->items, true);
        $pid = preg_replace('/\D/', '', (string) $request->input('pid', $request->input('nokartu', '')));
        $ket = trim((string) $request->input('ket', ''));
        $ket = trim(preg_replace('/\s+/u', ' ', $ket) ?? $ket);
        if (function_exists('mb_substr')) {
            $ket = mb_substr($ket, 0, 60, 'UTF-8');
        } else {
            $ket = substr($ket, 0, 60);
        }
        $ket = trim($ket);

        $failRedirect = function (string $msg) use ($cart) {
            $sessionData = $this->prepareCartSessionData(is_array($cart) ? $cart : []);
            return redirect('/kasir')
                ->with('error', $msg)
                ->with('cart_data', $sessionData['cart'])
                ->with('waiting_usage', $sessionData['waiting_usage'])
                ->with('waiting_confirmed', $sessionData['waiting_confirmed'])
                ->with('use_discount', true);
        };

        if ($pid === '' || strlen($pid) > 32) {
            return $failRedirect('NIS siswa tidak valid.');
        }
        if ($ket === '') {
            return $failRedirect('Keterangan barang wajib diisi (maks. 60 karakter).');
        }
        if (!$cart || count($cart) == 0) {
            return redirect('/kasir')->with('error', 'Keranjang kosong.');
        }

        // Cegah bypass: bayar hanya NIS yang baru di-inquiry + token one-time (3 menit)
        $gate = $this->consumeFaceChallenge($request, $pid);
        if (!$gate['ok']) {
            return $failRedirect($gate['error'] ?: 'Sesi FacePay tidak valid.');
        }

        if (!$this->faceSiswaEligible($pid)) {
            return $failRedirect('NIS tidak terdaftar FacePay / belum ada foto referensi.');
        }

        foreach ($cart as $c) {
            $qty = (int) ($c['qty'] ?? 0);
            if ($qty < 1 || $qty > 9999) {
                return $failRedirect('Qty barang tidak valid.');
            }
            if (empty($c['id'])) {
                return $failRedirect('Data keranjang tidak valid.');
            }
        }

        $lock = cache()->lock('facepay:pay:user:' . Auth::id(), 45);
        if (!$lock->get()) {
            return $failRedirect('Pembayaran FacePay sedang diproses. Tunggu sebentar.');
        }

        DB::beginTransaction();
        try {
            $items = [];
            $total = 0;
            $total_diskon = 0;
            $insufficientStock = [];

            foreach ($cart as $c) {
                $barang = Barang::where('id', $c['id'])->lockForUpdate()->first();
                if (!$barang) {
                    DB::rollBack();
                    $lock->release();
                    return $failRedirect('Barang tidak ditemukan!');
                }

                $qty_needed = (int) $c['qty'];

                $waitingList = WaitingBarang::where('barang_id', $barang->id)
                    ->orderBy('id', 'asc')
                    ->lockForUpdate()
                    ->get();

                $waitingTotal = $waitingList->sum('stok');
                $available_total = $barang->stok + $waitingTotal;

                if ($qty_needed > $available_total) {
                    $insufficientStock[] = [
                        'barang_id' => $barang->id,
                        'nama' => $barang->nama_barang,
                        'requested' => $qty_needed,
                        'available_qty' => $available_total,
                        'stok' => $barang->stok,
                    ];
                    continue;
                }

                $qty_base = min($qty_needed, $barang->stok);
                $waitingUsage = $c['waitingUsage'] ?? [];
                $groupedItems = [];

                $lastWaitingHargaJual = null;
                $lastWaitingHargaBeli = null;

                $waitingSum = 0;
                foreach ($waitingUsage as $wid => $wqty) {
                    $wqty = (int) $wqty;
                    if ($wqty <= 0) {
                        continue;
                    }
                    $waiting = $waitingList->firstWhere('id', (int) $wid);
                    if (!$waiting || (int) $waiting->barang_id !== (int) $barang->id) {
                        DB::rollBack();
                        $lock->release();
                        return $failRedirect('Data waiting barang tidak valid.');
                    }
                    $waitingSum += $wqty;
                }
                if ($waitingSum > max(0, $qty_needed - $qty_base)) {
                    DB::rollBack();
                    $lock->release();
                    return $failRedirect('Qty waiting tidak sesuai stok.');
                }

                if ($qty_base > 0) {
                    $key = $barang->harga_jual . '_' . $barang->harga_beli;
                    if (!isset($groupedItems[$key])) {
                        $groupedItems[$key] = [
                            'barang_id' => $barang->id,
                            'qty' => 0,
                            'harga' => $barang->harga_jual,
                            'harga_beli' => $barang->harga_beli,
                            'diskon' => $barang->diskon->nilai ?? 0,
                            'diskon_nominal' => 0,
                            'subtotal' => 0,
                            'profit' => 0,
                        ];
                    }
                    $groupedItems[$key]['qty'] += $qty_base;
                    $barang->stok -= $qty_base;
                    $barang->save();
                }

                foreach ($waitingUsage as $wid => $wqty) {
                    $wqty = (int) $wqty;
                    if ($wqty <= 0) {
                        continue;
                    }

                    $waiting = $waitingList->firstWhere('id', (int) $wid);
                    if (!$waiting || $waiting->stok < $wqty) {
                        DB::rollBack();
                        $lock->release();
                        return $failRedirect('Stok ' . $barang->nama_barang . ' tidak mencukupi!');
                    }

                    $key = $waiting->harga_jual . '_' . $waiting->harga_beli;
                    if (!isset($groupedItems[$key])) {
                        $groupedItems[$key] = [
                            'barang_id' => $barang->id,
                            'qty' => 0,
                            'harga' => $waiting->harga_jual,
                            'harga_beli' => $waiting->harga_beli,
                            'diskon' => $barang->diskon->nilai ?? 0,
                            'diskon_nominal' => 0,
                            'subtotal' => 0,
                            'profit' => 0,
                        ];
                    }
                    $groupedItems[$key]['qty'] += $wqty;

                    $lastWaitingHargaJual = $waiting->harga_jual;
                    $lastWaitingHargaBeli = $waiting->harga_beli;

                    $waiting->stok -= $wqty;
                    if ($waiting->stok <= 0) {
                        $waiting->delete();
                    } else {
                        $waiting->save();
                    }
                }

                foreach ($groupedItems as $item) {
                    $subtotal_sebelum = $item['harga'] * $item['qty'];
                    $diskon = floor(($item['diskon'] / 100) * $subtotal_sebelum);
                    $subtotal = $subtotal_sebelum - $diskon;
                    $modal = $item['harga_beli'] * $item['qty'];
                    $profit = $subtotal - $modal;

                    $item['diskon_nominal'] = $diskon;
                    $item['subtotal'] = $subtotal;
                    $item['profit'] = $profit;

                    $items[] = $item;
                    $total += $subtotal_sebelum;
                    $total_diskon += $diskon;
                }

                $remainingWaiting = WaitingBarang::where('barang_id', $barang->id)->orderBy('id', 'asc')->lockForUpdate()->get();
                if ($remainingWaiting->count() > 0) {
                    $nextWaiting = $remainingWaiting->first();
                    $barang->harga_beli = $nextWaiting->harga_beli;
                    $barang->harga_jual = $nextWaiting->harga_jual;
                    $barang->stok += $nextWaiting->stok;
                    $barang->save();
                    $nextWaiting->delete();
                } elseif ($lastWaitingHargaJual !== null && $lastWaitingHargaBeli !== null) {
                    $barang->harga_beli = $lastWaitingHargaBeli;
                    $barang->harga_jual = $lastWaitingHargaJual;
                    $barang->save();
                }
            }

            if (count($insufficientStock) > 0) {
                DB::rollBack();
                $lock->release();

                $errorMsg = '<div style="text-align:left; font-size:14px;">Stok tidak mencukupi untuk:<div style="margin-top:12px;">';
                foreach ($insufficientStock as $item) {
                    $errorMsg .= '<div style="margin-bottom:10px; padding:10px; border:1px solid #fee2e2; background:#fff1f2; border-radius:8px;">';
                    $errorMsg .= '<div style="font-weight:700; color:#991b1b;">' . $item['nama'] . '</div>';
                    $errorMsg .= '<div style="font-size:13px; color:#7f1d1d; margin-top:4px;">';
                    $errorMsg .= 'Diminta: <b>' . $item['requested'] . '</b> pcs<br>';
                    $errorMsg .= 'Stok tersedia: <b>' . $item['available_qty'] . '</b> pcs';
                    $errorMsg .= '</div></div>';
                }
                $errorMsg .= '</div></div>';

                $sessionData = $this->prepareCartSessionData($cart);
                return redirect('/kasir')
                    ->with('error', $errorMsg)
                    ->with('cart_data', $sessionData['cart'])
                    ->with('waiting_usage', $sessionData['waiting_usage'])
                    ->with('waiting_confirmed', $sessionData['waiting_confirmed'])
                    ->with('updated_stocks', $insufficientStock)
                    ->with('use_discount', true);
            }

            $grand_total = $total - $total_diskon;
            if ($grand_total < 1) {
                DB::rollBack();
                $lock->release();
                return $failRedirect('Total pembayaran tidak valid.');
            }

            $hintSaldo = (int) (($gate['challenge']['saldo'] ?? 0));
            if ($hintSaldo > 0 && $hintSaldo < $grand_total) {
                DB::rollBack();
                $lock->release();
                return $failRedirect(
                    'Saldo tidak mencukupi. Sisa saldo: Rp ' . number_format($hintSaldo, 0, ',', '.')
                );
            }

            try {
                $pay = MalangAlizzahClient::make()->paymentBelanjaWithKeterangan(
                    (string) $pid,
                    (int) $grand_total,
                    $ket
                );
            } catch (\Throwable $e) {
                DB::rollBack();
                $lock->release();
                return $failRedirect($this->formatErrorMessage('Pembayaran merchant gagal. Silakan coba lagi.'));
            }

            if (!$pay['ok']) {
                DB::rollBack();
                $lock->release();
                $errMsg = $pay['message'];
                if ($pay['result'] === 'SALDO_TAK_MENCUKUPI' && $pay['saldo'] !== null) {
                    $errMsg = 'Saldo tidak mencukupi. Sisa saldo: Rp ' . number_format((int) $pay['saldo'], 0, ',', '.');
                }
                return $failRedirect($this->formatErrorMessage($errMsg));
            }

            $return_id = null;

            $tanggal = Carbon::now('Asia/Jakarta');
            $kode = 'TRX-' . $tanggal->format('YmdHis') . '-' . substr(bin2hex(random_bytes(3)), 0, 6);

            $profit_total = 0;
            foreach ($items as $i) {
                $profit_total += $i['profit'];
            }

            $transaksi = Transaksi::create([
                'kode_transaksi' => $kode,
                'tanggal' => $tanggal,
                'total' => $total,
                'bayar' => $grand_total,
                'kembalian' => 0,
                'metode' => 'face',
                'pelanggan_id' => $pid,
                'diskon_nominal' => $total_diskon,
                'grand_total' => $grand_total,
                'profit' => $profit_total,
                'user_id' => Auth::id(),
            ]);

            foreach ($items as $i) {
                DetailTransaksi::create([
                    'transaksi_id' => $transaksi->id,
                    'barang_id' => $i['barang_id'],
                    'nama_barang' => Barang::find($i['barang_id'])->nama_barang,
                    'qty' => $i['qty'],
                    'harga' => $i['harga'],
                    'diskon' => $i['diskon'],
                    'diskon_nominal' => $i['diskon_nominal'],
                    'subtotal' => $i['subtotal'],
                    'profit' => $i['profit'],
                    'return' => $return_id,
                ]);
            }

            DB::commit();
            $lock->release();
            $suksesMsg = 'Pembayaran FacePay berhasil!';
            if (!empty($pay['nama'])) {
                $suksesMsg .= ' ' . $pay['nama'];
            }
            if ($pay['saldo'] !== null) {
                $suksesMsg .= ' · Sisa saldo: Rp ' . number_format((int) $pay['saldo'], 0, ',', '.');
            }
            return redirect('/kasir')->with('success', $suksesMsg);
        } catch (\Exception $e) {
            DB::rollBack();
            $lock->release();
            return redirect('/kasir')->with('error', 'Kesalahan sistem. Silakan coba lagi.');
        }
    }



    public function laporan()
    {
        $transaksis = Transaksi::with('user')
            ->orderBy('tanggal', 'desc')
            ->paginate(10);

        return view('admin.laporan.index', compact('transaksis'));
    }

    public function search(Request $request)
    {
        $query = Transaksi::with('user');

        if ($request->tanggal_dari) {
            $query->whereDate('tanggal', '>=', $request->tanggal_dari);
        }

        if ($request->tanggal_sampai) {
            $query->whereDate('tanggal', '<=', $request->tanggal_sampai);
        }

        if ($request->metode) {
            $query->where('metode', $request->metode);
        }

        $transaksis = $query->orderBy('tanggal', 'desc')->paginate(10);
        $transaksis->appends($request->all());

        return view('admin.laporan.index', compact('transaksis'));
    }

    public function cetakPdf(Request $request)
    {
        $query = Transaksi::with(['user', 'detail.barang']);

        if ($request->tanggal_dari) {
            $query->whereDate('tanggal', '>=', $request->tanggal_dari);
        }

        if ($request->tanggal_sampai) {
            $query->whereDate('tanggal', '<=', $request->tanggal_sampai);
        }

        if ($request->metode) {
            $query->where('metode', $request->metode);
        }

        if ($request->diskon_min || $request->diskon_max) {
            $query->whereHas('detail', function ($q) use ($request) {
                if ($request->diskon_min) {
                    $q->where('diskon', '>=', $request->diskon_min);
                }
                if ($request->diskon_max) {
                    $q->where('diskon', '<=', $request->diskon_max);
                }
            });
        }

        $transaksis = $query->orderBy('tanggal', 'desc')->get();

        $topBarang = DB::table('detail_transaksi')
            ->join('barangs', 'detail_transaksi.barang_id', '=', 'barangs.id')
            ->select('barangs.nama_barang as nama', DB::raw('SUM(detail_transaksi.qty) as total_terjual'))
            ->groupBy('barangs.nama_barang')
            ->orderBy('total_terjual', 'desc')
            ->limit(3)
            ->get();

        $pdf = Pdf::loadView('admin.laporan.pdf', compact('transaksis', 'topBarang'))->setPaper('A4', 'portrait');
        return $pdf->download('laporan-transaksi.pdf');
    }
}
