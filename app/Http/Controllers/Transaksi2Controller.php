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
use Illuminate\Support\Facades\Http;

class Transaksi2Controller extends Controller
{
    public function index()
    {
        $barangs = Barang::with('diskon')
            ->where('stok', '>', 0)
            ->where('status', 'aktif')
            ->get();
    
        return view('kasir.index2', compact('barangs'));
    }

    private function rebuildCart($cart)
    {
        $newCart = [];

        foreach ($cart as $item) {
            $barang = Barang::find($item['id']);
            if ($barang) {
                $newCart[] = [
                    'id' => $item['id'],
                    'kode_barang' => $barang->kode_barang,
                    'name' => $barang->nama_barang,
                    'price' => $barang->harga_jual,
                    'stok' => $barang->stok,
                    'diskon' => $barang->diskon->nilai ?? 0,
                    'qty' => $item['qty']
                ];
            }
        }

        return $newCart;
    }

    public function processTunai(Request $request)
    {
        $request->validate([
            'cart' => 'required|json',
            'bayar' => 'required|string'
        ]);

        DB::beginTransaction();

        try {
            $cart = json_decode($request->cart, true);

            if (!$cart || count($cart) == 0) {
                return redirect('/kasir2')->with('error', 'Keranjang kosong.');
            }

            $items = [];
            $total = 0;
            $total_modal = 0;
            $errors = [];

            foreach ($cart as $c) {
                if (!isset($c['id']) || !isset($c['qty'])) {
                    DB::rollBack();
                    return redirect('/kasir2')->with('error', 'Data keranjang tidak valid.');
                }

                $barang = Barang::where('id', $c['id'])->lockForUpdate()->first();

                if (!$barang) {
                    DB::rollBack();
                    return redirect('/kasir2')
                        ->with('error', 'Barang tidak ditemukan!')
                        ->with('cart_data', $this->rebuildCart($cart));
                }

                if ($barang->stok < $c['qty']) {
                    $errors[] = "Stok {$barang->nama_barang} tidak mencukupi! Stok tersedia: {$barang->stok}";
                }
            }

            if (!empty($errors)) {
                DB::rollBack();
                return redirect('/kasir2')
                    ->with('error', implode('<br>', $errors))
                    ->with('cart_data', $this->rebuildCart($cart));
            }

            foreach ($cart as $c) {
                $barang = Barang::where('id', $c['id'])->lockForUpdate()->first();

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

            $bayar = (int) str_replace(['Rp ', '.', ' '], '', $request->bayar);

            if ($bayar < $total) {
                DB::rollBack();
                return redirect('/kasir2')
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
                'diskon_nominal' => 0,
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
                    'diskon' => 0,
                    'diskon_nominal' => 0,
                    'subtotal' => $i['subtotal']
                ]);

                $barang = Barang::find($i['id']);
                $barang->stok -= $i['qty'];
                $barang->save();
            }

            DB::commit();
            return redirect('/kasir2')->with('success', 'Transaksi berhasil! Kode: ' . $kode);

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect('/kasir2')->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
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

        if (stripos($message, 'PIN salah') !== false) {
            return 'PIN yang Anda masukkan salah';
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
        $request->validate([
            'items' => 'required|json',
            'pid' => 'required|string',
            'pin' => 'required|string'
        ]);

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
            $total_modal = 0;

            foreach ($cart as $c) {
                if (!isset($c['id']) || !isset($c['qty'])) {
                    DB::rollBack();
                    return redirect('/kasir2')->with('error', 'Data keranjang tidak valid.');
                }

                $barang = Barang::where('id', $c['id'])->lockForUpdate()->first();

                if (!$barang) {
                    DB::rollBack();
                    return redirect('/kasir2')
                        ->with('error', 'Barang tidak ditemukan!')
                        ->with('cart_data', $this->rebuildCart($cart));
                }

                if ($barang->stok < $c['qty']) {
                    DB::rollBack();
                    return redirect('/kasir2')
                        ->with('error', "Stok {$barang->nama_barang} tidak mencukupi! Stok tersedia: {$barang->stok}")
                        ->with('cart_data', $this->rebuildCart($cart));
                }

                $subtotal = $barang->harga_jual * $c['qty'];
                $diskon_persen = $barang->diskon->nilai ?? 0;
                $diskon_nominal = floor(($diskon_persen / 100) * $subtotal);
                $modal = $barang->harga_beli * $c['qty'];

                $items[] = [
                    'id' => $barang->id,
                    'qty' => $c['qty'],
                    'harga' => $barang->harga_jual,
                    'diskon' => $diskon_persen,
                    'diskon_nominal' => $diskon_nominal,
                    'subtotal' => $subtotal - $diskon_nominal,
                    'modal' => $modal
                ];

                $total += $subtotal;
                $total_diskon += $diskon_nominal;
                $total_modal += $modal;
            }

            $grand_total = $total - $total_diskon;

            $payload = [
                "pid" => $pid,
                "pin" => $pin,
                "nominal" => intval($grand_total)
            ];

            $secretKey = "53c2f9aariasb60akenoa3dc29b60c3e1gremorye3c1701f4355fa4";
            $jwtToken = JWT::encode($payload, $secretKey, 'HS256');

            $response = Http::timeout(30)->withHeaders([
                'Content-Type' => 'application/json',
            ])->post("http://10.99.23.111/WS_CLIENT/DEMO_POS/index.php", [
                "method" => "debetCashWithPin",
                "token" => $jwtToken
            ]);

            if (!$response->successful()) {
                DB::rollBack();
                $res = $response->json();
                $errorMsg = $res['message'] ?? 'Server pembayaran bermasalah';
                return redirect('/kasir2')
                    ->with('error', $this->formatErrorMessage($errorMsg))
                    ->with('cart_data', $this->rebuildCart($cart));
            }

            $res = $response->json();

            if (!isset($res['status']) || $res['status'] != 200) {
                DB::rollBack();
                $errorMsg = $res['message'] ?? 'Pembayaran gagal';
                return redirect('/kasir2')
                    ->with('error', $this->formatErrorMessage($errorMsg))
                    ->with('cart_data', $this->rebuildCart($cart));
            }

            $tanggal = Carbon::now('Asia/Jakarta');
            $kode_transaksi = 'TRX-' . $tanggal->format('YmdHis');
            $profit = ($total - $total_diskon) - $total_modal;

            $transaksi = Transaksi::create([
                'kode_transaksi' => $kode_transaksi,
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
            return redirect('/kasir2')->with('success', 'Pembayaran berhasil!');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect('/kasir2')
                ->with('error', 'Terjadi kesalahan sistem: ' . $e->getMessage());
        }
    }
}