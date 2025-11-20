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
                'kode_barang' => $item['kode_barang'],
                'name' => $item['name'],
                'price' => $item['harga'],
                'stok' => $barang ? $barang->stok : 0,
                'diskon' => $item['diskon'],
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

            $errors = [];

            foreach ($cart as $item) {
                $barang = Barang::where('id', $item['id'])->lockForUpdate()->first();

                if ($barang->stok < $item['qty']) {
                    $errors[] = "Stok {$barang->nama_barang} tidak mencukupi! Stok tersedia: {$barang->stok}";
                }
            }

            if (!empty($errors)) {
                DB::rollBack();
                return redirect('/kasir')
                    ->with('error', implode('<br>', $errors))
                    ->with('cart_data', $this->rebuildCart($cart));
            }

            $total = 0;
            $total_modal = 0;

            foreach ($cart as $item) {
                $total += $item['subtotal'];
                $barang = Barang::where('id', $item['id'])->lockForUpdate()->first();
                $total_modal += ($barang->harga_beli * $item['qty']);
            }

            $bayar = (int) str_replace(['Rp ', '.'], '', $request->bayar);
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

            foreach ($cart as $item) {
                $barang = Barang::where('id', $item['id'])->lockForUpdate()->first();

                DetailTransaksi::create([
                    'transaksi_id' => $transaksi->id,
                    'barang_id' => $item['id'],
                    'qty' => $item['qty'],
                    'harga' => $item['harga'],
                    'subtotal' => $item['subtotal']
                ]);

                $barang->stok -= $item['qty'];
                $barang->save();
            }

            DB::commit();
            return redirect('/kasir')->with('success', 'Transaksi berhasil disimpan!');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect('/kasir')->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
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
                $saldoSekarang = number_format($matches[1], 0, ',', '.');
                return "Saldo tidak cukup. Saldo Anda: Rp {$saldoSekarang}";
            }
            return 'Saldo tidak mencukupi';
        }

        return $message;
    }

    public function processOnline(Request $request)
    {
        DB::beginTransaction();

        try {
            $items = json_decode($request->items, true);
            $total = $request->total;
            $diskon_nominal = $request->diskon_nominal;
            $grand_total = $request->grand_total;
            $pid = $request->pid;
            $cart_data = json_decode($request->cart_data, true);

            foreach ($items as $item) {
                $barang = Barang::where('id', $item['id'])->lockForUpdate()->first();

                if (!$barang || $barang->stok < $item['qty']) {
                    DB::rollBack();
                    return redirect('/kasir')
                        ->with('error', "Stok {$barang->nama_barang} tidak mencukupi! Stok tersedia: {$barang->stok}")
                        ->with('cart_data', $this->rebuildCart($items));
                }
            }

            $payload = [
                "pid"  => $pid,
                "nominal" => intval($grand_total)
            ];

            $secretKey = "53c2f9aariasb60akenoa3dc29b60c3e1gremorye3c1701f4355fa4";
            $jwtToken  = JWT::encode($payload, $secretKey, 'HS256');

            $response = Http::timeout(30)->withHeaders([
                'Content-Type' => 'application/json',
            ])->post("http://localhost/WS_SERVER/index.php", [
                "method" => "debetCash",
                "token"  => $jwtToken
            ]);

            if (!$response->successful()) {
                DB::rollBack();
                $res = $response->json();
                $errorMsg = $res['message'] ?? 'Server pembayaran bermasalah';
                return redirect('/kasir')
                    ->with('error', $this->formatErrorMessage($errorMsg))
                    ->with('cart_data', $this->rebuildCart($items));
            }

            $res = $response->json();

            if (!isset($res['status']) || $res['status'] != 200) {
                DB::rollBack();
                $errorMsg = $res['message'] ?? 'Pembayaran gagal';
                return redirect('/kasir')
                    ->with('error', $this->formatErrorMessage($errorMsg))
                    ->with('cart_data', $cart_data);
            }

            $kode_transaksi = 'TRX-' . date('YmdHis');

            $profit = 0;
            foreach ($items as $item) {
                $barang = Barang::where('id', $item['id'])->lockForUpdate()->first();
                $hargaSetelahDiskon = $barang->harga_jual - ($item['diskon_nominal'] / $item['qty']);
                $profit += ($hargaSetelahDiskon - $barang->harga_beli) * $item['qty'];
            }

            $transaksi = Transaksi::create([
                'kode_transaksi' => $kode_transaksi,
                'tanggal' => now(),
                'total' => $total,
                'bayar' => $grand_total,
                'kembalian' => 0,
                'metode' => 'online',
                'pelanggan_id' => null,
                'diskon_nominal' => $diskon_nominal,
                'grand_total' => $grand_total,
                'profit' => $profit,
                'user_id' => Auth::id()
            ]);

            foreach ($items as $item) {
                $barang = Barang::where('id', $item['id'])->lockForUpdate()->first();

                DetailTransaksi::create([
                    'transaksi_id' => $transaksi->id,
                    'barang_id' => $item['id'],
                    'qty' => $item['qty'],
                    'harga' => $item['harga'],
                    'diskon' => $item['diskon'],
                    'diskon_nominal' => $item['diskon_nominal'],
                    'subtotal' => $item['subtotal']
                ]);

                $barang->stok -= $item['qty'];
                $barang->save();
            }

            DB::commit();
            return redirect('/kasir')->with('success', 'Pembayaran berhasil! Kode: ' . $kode_transaksi);

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect('/kasir')
                ->with('error', 'Terjadi kesalahan sistem: ' . $e->getMessage())
                ->with('cart_data', $cart_data ?? []);
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
