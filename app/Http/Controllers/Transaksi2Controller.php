<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Transaksi;
use App\Models\DetailTransaksi;
use App\Models\Barang;
use App\Models\WaitingBarang;
use App\Services\BatuAlizzahClient;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class Transaksi2Controller extends Controller
{
    public function index()
    {
        $barangs = Barang::with('diskon')->where('status', 'aktif')->get();
        $waiting_barangs = WaitingBarang::with('barang')->get();
        return view('kasir.index2', compact('barangs', 'waiting_barangs'));
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
                $total = array_sum($item['waitingUsage']);
                $waitingUsage[$barang->id] = [
                    'total' => $total,
                    'perWaiting' => $item['waitingUsage']
                ];

                foreach ($item['waitingUsage'] as $wid => $qty) {
                    if ($qty > 0) {
                        if (!isset($waitingConfirmed[$barang->id])) {
                            $waitingConfirmed[$barang->id] = [];
                        }
                        $waitingConfirmed[$barang->id][$wid] = true;
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
        if (stripos($message, 'pin salah') !== false) return "PIN yang Anda masukkan salah";
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
    public function processTunai(Request $request)
    {
        DB::beginTransaction();
        try {
            $cart = json_decode($request->cart, true);
            $bayar = (int) str_replace(['Rp', '.', ' '], '', $request->bayar);

            if (!$cart || count($cart) == 0) {
                return redirect('/kasir2')->with('error', 'Keranjang kosong.');
            }

            $items = [];
            $total = 0;
            $insufficientStock = [];

            foreach ($cart as $c) {
                $barang = Barang::where('id', $c['id'])->lockForUpdate()->first();
                if (!$barang) {
                    DB::rollBack();
                    return redirect('/kasir2')->with('error', 'Barang tidak ditemukan!');
                }

                $qty_needed = $c['qty'];

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
                        'stok' => $barang->stok
                    ];
                }
            }

            if (!empty($insufficientStock)) {
                DB::rollBack();
                $errorMsg = '<div style="text-align:left;">';
                $errorMsg .= '<p style="font-weight:bold; margin-bottom:10px;">Stok tidak mencukupi:</p>';
                foreach ($insufficientStock as $item) {
                    $errorMsg .= '<div style="background:#fee2e2; padding:8px; border-radius:6px; margin-bottom:8px;">';
                    $errorMsg .= '<div style="font-weight:600; color:#991b1b;">' . $item['nama'] . '</div>';
                    $errorMsg .= '<div style="font-size:13px; color:#7f1d1d;">Diminta: <b>' . $item['requested'] . '</b> pcs | ';
                    $errorMsg .= 'Tersedia: <b>' . $item['available_qty'] . '</b> pcs</div>';
                    $errorMsg .= '</div>';
                }
                $errorMsg .= '</div>';

                $sessionData = $this->prepareCartSessionData($cart);
                return redirect('/kasir2')
                    ->with('error', $errorMsg)
                    ->with('cart_data', $sessionData['cart'])
                    ->with('waiting_usage', $sessionData['waiting_usage'])
                    ->with('waiting_confirmed', $sessionData['waiting_confirmed'])
                    ->with('updated_stocks', $insufficientStock);
            }

            foreach ($cart as $c) {
                $barang = Barang::where('id', $c['id'])->lockForUpdate()->first();
                $qty_needed = $c['qty'];

                $waitingList = WaitingBarang::where('barang_id', $barang->id)
                    ->orderBy('id', 'asc')
                    ->lockForUpdate()
                    ->get();

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
                            'diskon' => 0,
                            'diskon_nominal' => 0,
                            'subtotal' => 0,
                            'profit' => 0
                        ];
                    }
                    $groupedItems[$key]['qty'] += $qty_base;
                    $barang->stok -= $qty_base;
                    $barang->save();
                }

                foreach ($waitingUsage as $wid => $wqty) {
                    if ($wqty <= 0) continue;

                    $waiting = $waitingList->firstWhere('id', $wid);
                    if (!$waiting || $waiting->stok < $wqty) {
                        DB::rollBack();
                        return redirect('/kasir2')->with('error', 'Stok ' . $barang->nama_barang . ' tidak mencukupi!');
                    }

                    $key = $waiting->harga_jual . '_' . $waiting->harga_beli;
                    if (!isset($groupedItems[$key])) {
                        $groupedItems[$key] = [
                            'barang_id' => $barang->id,
                            'qty' => 0,
                            'harga' => $waiting->harga_jual,
                            'harga_beli' => $waiting->harga_beli,
                            'diskon' => 0,
                            'diskon_nominal' => 0,
                            'subtotal' => 0,
                            'profit' => 0
                        ];
                    }

                    $groupedItems[$key]['qty'] += $wqty;
                    $lastWaitingHargaJual = $waiting->harga_jual;
                    $lastWaitingHargaBeli = $waiting->harga_beli;

                    $waiting->stok -= $wqty;
                    if ($waiting->stok <= 0) $waiting->delete();
                    else $waiting->save();
                }

                foreach ($groupedItems as $item) {
                    $subtotal = $item['harga'] * $item['qty'];
                    $modal = $item['harga_beli'] * $item['qty'];
                    $profit = $subtotal - $modal;

                    $item['subtotal'] = $subtotal;
                    $item['profit'] = $profit;

                    $items[] = $item;
                    $total += $subtotal;
                }

                $remainingWaiting = WaitingBarang::where('barang_id', $barang->id)
                    ->orderBy('id', 'asc')
                    ->lockForUpdate()
                    ->get();

                if ($remainingWaiting->count() > 0) {
                    $nextWaiting = $remainingWaiting->first();
                    $barang->harga_beli = $nextWaiting->harga_beli;
                    $barang->harga_jual = $nextWaiting->harga_jual;
                    $barang->stok += $nextWaiting->stok;
                    $barang->save();
                    $nextWaiting->delete();
                } else {
                    if ($lastWaitingHargaJual !== null && $lastWaitingHargaBeli !== null) {
                        $barang->harga_jual = $lastWaitingHargaJual;
                        $barang->harga_beli = $lastWaitingHargaBeli;
                        $barang->save();
                    }
                }
            }

            if ($bayar < $total) {
                DB::rollBack();
                return redirect('/kasir2')->with('error', 'Uang bayar kurang!');
            }

            $kembalian = $bayar - $total;
            $tanggal = Carbon::now('Asia/Jakarta');
            $kode = 'TRX-' . $tanggal->format('YmdHis');

            $profit_total = 0;
            foreach ($items as $i) $profit_total += $i['profit'];

            $transaksi = Transaksi::create([
                'kode_transaksi' => $kode,
                'tanggal' => $tanggal,
                'total' => $total,
                'bayar' => $bayar,
                'kembalian' => $kembalian,
                'metode' => 'tunai',
                'diskon_nominal' => 0,
                'grand_total' => $total,
                'profit' => $profit_total,
                'user_id' => Auth::id()
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
                    'profit' => $i['profit']
                ]);
            }

            DB::commit();
            return redirect('/kasir2')->with('success', 'Transaksi berhasil!');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect('/kasir2')->with('error', 'Kesalahan sistem: ' . $e->getMessage());
        }
    }

    public function processOnline(Request $request)
    {
        DB::beginTransaction();
        try {
            $cart = json_decode($request->items, true);
            $pid = $request->pid;
            $pin = $request->pin;

            if (!$cart || count($cart) == 0) {
                return redirect('/kasir2')->with('error', 'Keranjang kosong.');
            }

            $items = [];
            $total = 0;
            $total_diskon = 0;
            $insufficientStock = [];

            foreach ($cart as $c) {
                $barang = Barang::where('id', $c['id'])->lockForUpdate()->first();
                if (!$barang) {
                    DB::rollBack();
                    return redirect('/kasir2')->with('error', 'Barang tidak ditemukan!');
                }

                $qty_needed = $c['qty'];

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
                        'stok' => $barang->stok
                    ];
                }
            }

            if (!empty($insufficientStock)) {
                DB::rollBack();

                $errorMsg = '<div style="text-align:left;">';
                $errorMsg .= '<p style="font-weight:bold; margin-bottom:10px;">Stok tidak mencukupi:</p>';
                foreach ($insufficientStock as $item) {
                    $errorMsg .= '<div style="background:#fee2e2; padding:8px; border-radius:6px; margin-bottom:8px;">';
                    $errorMsg .= '<div style="font-weight:600; color:#991b1b;">' . $item['nama'] . '</div>';
                    $errorMsg .= '<div style="font-size:13px; color:#7f1d1d;">Diminta: <b>' . $item['requested'] . '</b> pcs | ';
                    $errorMsg .= 'Tersedia: <b>' . $item['available_qty'] . '</b> pcs</div>';
                    $errorMsg .= '</div>';
                }
                $errorMsg .= '</div>';

                $sessionData = $this->prepareCartSessionData($cart);
                return redirect('/kasir2')
                    ->with('error', $errorMsg)
                    ->with('cart_data', $sessionData['cart'])
                    ->with('waiting_usage', $sessionData['waiting_usage'])
                    ->with('waiting_confirmed', $sessionData['waiting_confirmed'])
                    ->with('use_discount', true)
                    ->with('updated_stocks', $insufficientStock);
            }

            foreach ($cart as $c) {
                $barang = Barang::where('id', $c['id'])->lockForUpdate()->first();
                $qty_needed = $c['qty'];

                $waitingList = WaitingBarang::where('barang_id', $barang->id)
                    ->orderBy('id', 'asc')
                    ->lockForUpdate()
                    ->get();

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
                            'profit' => 0
                        ];
                    }
                    $groupedItems[$key]['qty'] += $qty_base;
                    $barang->stok -= $qty_base;
                    $barang->save();
                }

                foreach ($waitingUsage as $wid => $wqty) {
                    if ($wqty <= 0) continue;

                    $waiting = $waitingList->firstWhere('id', $wid);
                    if (!$waiting || $waiting->stok < $wqty) {
                        DB::rollBack();
                        $sessionData = $this->prepareCartSessionData($cart);
                        return redirect('/kasir2')
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
                            'profit' => 0
                        ];
                    }

                    $groupedItems[$key]['qty'] += $wqty;
                    $lastWaitingHargaJual = $waiting->harga_jual;
                    $lastWaitingHargaBeli = $waiting->harga_beli;

                    $waiting->stok -= $wqty;
                    if ($waiting->stok <= 0) $waiting->delete();
                    else $waiting->save();
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

                $remainingWaiting = WaitingBarang::where('barang_id', $barang->id)
                    ->orderBy('id', 'asc')
                    ->lockForUpdate()
                    ->get();

                if ($remainingWaiting->count() > 0) {
                    $nextWaiting = $remainingWaiting->first();
                    $barang->harga_beli = $nextWaiting->harga_beli;
                    $barang->harga_jual = $nextWaiting->harga_jual;
                    $barang->stok += $nextWaiting->stok;
                    $barang->save();
                    $nextWaiting->delete();
                } else {
                    if ($lastWaitingHargaJual !== null && $lastWaitingHargaBeli !== null) {
                        $barang->harga_jual = $lastWaitingHargaJual;
                        $barang->harga_beli = $lastWaitingHargaBeli;
                        $barang->save();
                    }
                }
            }

            $grand_total = $total - $total_diskon;

            try {
                $pay = BatuAlizzahClient::make()->paymentBelanja((string) $pid, (int) $grand_total, $pin ? (string) $pin : null);
            } catch (\Throwable $e) {
                DB::rollBack();
                $sessionData = $this->prepareCartSessionData($cart);
                return redirect('/kasir2')
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
                return redirect('/kasir2')
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
                'user_id' => Auth::id()
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
                    'return' => $return_id
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
            return redirect('/kasir2')->with('success', $suksesMsg);
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect('/kasir2')->with('error', 'Kesalahan sistem: ' . $e->getMessage());
        }
    }
}
