<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Transaksi;
use App\Models\DetailTransaksi;
use App\Models\Barang;
use App\Models\WaitingBarang;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Http;

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

            $payload = [
                "pid" => $pid,
                "nominal" => $grand_total,
            ];

            $secretKey = "53c2f9aariasb60akenoa3dc29b60c3e1gremorye3c1701f4355fa4";
            $jwtToken = JWT::encode($payload, $secretKey, 'HS256');

            $response = Http::timeout(30)->withHeaders([
                'Content-Type' => 'application/json',
            ])->post("http://10.99.23.111/WS_CLIENT/DEMO_POS/index.php", [
                "method" => "debetCash",
                "token" => $jwtToken,
            ]);

            if (!$response->successful()) {
                DB::rollBack();
                $res = $response->json();
                $sessionData = $this->prepareCartSessionData($cart);
                return redirect('/kasir')
                    ->with('error', $this->formatErrorMessage($res['message'] ?? 'Server pembayaran bermasalah'))
                    ->with('cart_data', $sessionData['cart'])
                    ->with('waiting_usage', $sessionData['waiting_usage'])
                    ->with('waiting_confirmed', $sessionData['waiting_confirmed'])
                    ->with('use_discount', true);
            }

            $res = $response->json();
            if (!isset($res['status']) || $res['status'] != 200) {
                DB::rollBack();
                $sessionData = $this->prepareCartSessionData($cart);
                return redirect('/kasir')
                    ->with('error', $this->formatErrorMessage($res['message'] ?? 'Pembayaran gagal'))
                    ->with('cart_data', $sessionData['cart'])
                    ->with('waiting_usage', $sessionData['waiting_usage'])
                    ->with('waiting_confirmed', $sessionData['waiting_confirmed'])
                    ->with('use_discount', true);
            }

            $msg = explode('|', $res['message']);
            $return_id = $msg[3] ?? null;

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
            return redirect('/kasir')->with('success', 'Pembayaran berhasil!');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect('/kasir')->with('error', 'Kesalahan sistem: ' . $e->getMessage());
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
