<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Transaksi;
use App\Models\DetailTransaksi;
use App\Models\Barang;
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

    private function rebuildCart($cart)
    {
        $newCart = [];

        foreach ($cart as $item) {
            $barang = Barang::find($item['id']);
            $newCart[] = [
                'id' => $item['id'],
                'kode_barang' => $barang->kode_barang ?? '',
                'name' => $barang->nama_barang ?? '',
                'price' => $barang->harga_jual ?? 0,
                'stok' => $barang ? $barang->stok : 0,
                'diskon' => $barang->diskon->nilai ?? 0,
                'qty' => $item['qty']
            ];
        }

        return $newCart;
    }

    public function processTunai(Request $request)
    {
        DB::beginTransaction();

        try {
            $cart = json_decode($request->cart, true);

            if (!$cart || count($cart) == 0) {
                return redirect('/kasir')->with('error', 'Keranjang kosong.');
            }

            $items = [];
            $total = 0;
            $total_modal = 0;

            foreach ($cart as $c) {
                $barang = Barang::where('id', $c['id'])->lockForUpdate()->first();
                if (!$barang || $barang->stok < $c['qty']) {
                    DB::rollBack();
                    return redirect('/kasir')
                        ->with('error', 'Stok ' . ($barang->nama_barang ?? '') . ' tidak mencukupi!')
                        ->with('cart_data', $this->rebuildCart($cart));
                }

                $subtotal = $barang->harga_jual * $c['qty'];
                $modal = $barang->harga_beli * $c['qty'];

                $items[] = [
                    'id' => $barang->id,
                    'qty' => $c['qty'],
                    'harga' => $barang->harga_jual,
                    'subtotal' => $subtotal
                ];

                $total += $subtotal;
                $total_modal += $modal;
            }

            $bayar = (int) str_replace(['Rp ', '.'], '', $request->bayar);
            if ($bayar < $total) {
                DB::rollBack();
                return redirect('/kasir')
                    ->with('error', 'Uang kurang dari total pembayaran.')
                    ->with('cart_data', $this->rebuildCart($cart));
            }

            $kembalian = $bayar - $total;
            $tanggal = Carbon::now('Asia/Jakarta');
            $kode = 'TRX-' . $tanggal->format('YmdHis');
            $profit = $total - $total_modal;

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
                'profit' => $profit,
                'user_id' => Auth::id()
            ]);

            foreach ($items as $i) {
                DetailTransaksi::create([
                    'transaksi_id' => $transaksi->id,
                    'barang_id' => $i['id'],
                    'qty' => $i['qty'],
                    'harga' => $i['harga'],
                    'subtotal' => $i['subtotal']
                ]);

                $barang = Barang::find($i['id']);
                $barang->stok -= $i['qty'];
                $barang->save();
            }

            DB::commit();
            return redirect('/kasir')->with('success', 'Transaksi berhasil disimpan!');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect('/kasir')->with('error', 'Kesalahan: ' . $e->getMessage());
        }
    }

    private function formatErrorMessage($message)
    {
        if (stripos($message, 'siswa tidak ditemukan') !== false || stripos($message, 'customer tidak ditemukan') !== false) {
            return 'Kartu tidak terdaftar';
        }

        if (stripos($message, 'pin') !== false && stripos($message, 'blokir') !== false) {
            return 'Kartu telah diblokir';
        }

        if (stripos($message, 'saldo') !== false && stripos($message, 'tidak') !== false) {
            preg_match('/Saldo saat ini: (\d+)/', $message, $matches);
            if (isset($matches[1])) {
                return "Saldo tidak cukup. Saldo Anda: Rp " . number_format($matches[1], 0, ',', '.');
            }
            return 'Saldo tidak mencukupi';
        }

        return $message;
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

            foreach ($cart as $c) {
                $barang = Barang::where('id', $c['id'])->lockForUpdate()->first();
                if (!$barang || $barang->stok < $c['qty']) {
                    DB::rollBack();
                    return redirect('/kasir')
                        ->with('error', 'Stok ' . ($barang->nama_barang ?? '') . ' tidak mencukupi!')
                        ->with('cart_data', $this->rebuildCart($cart));
                }

                $subtotal = $barang->harga_jual * $c['qty'];
                $diskon_nominal = floor(($barang->diskon->nilai ?? 0) / 100 * $subtotal);

                $items[] = [
                    'id' => $barang->id,
                    'qty' => $c['qty'],
                    'harga' => $barang->harga_jual,
                    'diskon' => $barang->diskon->nilai ?? 0,
                    'diskon_nominal' => $diskon_nominal,
                    'subtotal' => $subtotal - $diskon_nominal
                ];

                $total += $subtotal;
                $total_diskon += $diskon_nominal;
            }

            $grand_total = $total - $total_diskon;

            $payload = [
                "pid"  => $pid,
                "nominal" => $grand_total
            ];

            $secretKey = "53c2f9aariasb60akenoa3dc29b60c3e1gremorye3c1701f4355fa4";
            $jwtToken  = JWT::encode($payload, $secretKey, 'HS256');

            $response = Http::timeout(30)->withHeaders([
                'Content-Type' => 'application/json',
            ])->post("http://10.99.23.111/WS_CLIENT/DEMO_POS/index.php", [
                "method" => "debetCash",
                "token"  => $jwtToken
            ]);

            if (!$response->successful()) {
                DB::rollBack();
                $res = $response->json();
                return redirect('/kasir')
                    ->with('error', $this->formatErrorMessage($res['message'] ?? 'Server pembayaran bermasalah'))
                    ->with('cart_data', $this->rebuildCart($cart));
            }

            $res = $response->json();
            if (!isset($res['status']) || $res['status'] != 200) {
                DB::rollBack();
                return redirect('/kasir')
                    ->with('error', $this->formatErrorMessage($res['message'] ?? 'Pembayaran gagal'))
                    ->with('cart_data', $this->rebuildCart($cart));
            }

            $tanggal = Carbon::now('Asia/Jakarta');
            $kode = 'TRX-' . $tanggal->format('YmdHis');

            $profit = 0;
            foreach ($items as $i) {
                $barang = Barang::where('id', $i['id'])->lockForUpdate()->first();
                $hargaSetelahDiskon = $i['subtotal'] / $i['qty'];
                $profit += ($hargaSetelahDiskon - $barang->harga_beli) * $i['qty'];
            }

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
                'profit' => $profit,
                'user_id' => Auth::id()
            ]);

            foreach ($items as $i) {
                DetailTransaksi::create([
                    'transaksi_id' => $transaksi->id,
                    'barang_id' => $i['id'],
                    'qty' => $i['qty'],
                    'harga' => $i['harga'],
                    'diskon' => $i['diskon'],
                    'diskon_nominal' => $i['diskon_nominal'],
                    'subtotal' => $i['subtotal']
                ]);

                $barang = Barang::find($i['id']);
                $barang->stok -= $i['qty'];
                $barang->save();
            }

            DB::commit();
            return redirect('/kasir')->with('success', 'Pembayaran berhasil! Kode: ' . $kode);
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect('/kasir')
                ->with('error', 'Kesalahan sistem: ' . $e->getMessage());
        }
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

        $transaksis = $query->orderBy('tanggal', 'desc')->get();
        $pdf = Pdf::loadView('admin.laporan.pdf', compact('transaksis'))->setPaper('A4', 'portrait');
        return $pdf->download('laporan-transaksi.pdf');
    }

    public function laporan()
    {
        $transaksis = Transaksi::with('user')
            ->orderBy('tanggal', 'desc')
            ->paginate(10);

        return view('admin.laporan.index', compact('transaksis'));
    }
}