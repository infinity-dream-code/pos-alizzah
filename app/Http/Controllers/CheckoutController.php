<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Barang;
use App\Models\Diskon;

class CheckoutController extends Controller
{
    public function tunai(Request $request)
    {
        return $this->processCheckout($request, 'tunai');
    }

    public function online(Request $request)
    {
        return $this->processCheckout($request, 'online');
    }

    private function processCheckout(Request $request, $method)
    {
        $items = json_decode($request->items, true);

        if (!$items) {
            return redirect('/kasir')->with('error', 'Keranjang kosong!');
        }

        $total_sebelum_diskon = 0;
        $total_diskon = 0;
        $detail = [];

        foreach ($items as $i) {
            $barang = Barang::find($i['id']);
            if (!$barang) continue;

            if ($i['qty'] > $barang->stok) {
                return redirect('/kasir')->with('error', 'Stok tidak cukup!');
            }

            $diskon = Diskon::where('barang_id', $barang->id)
                ->where('aktif', 1)
                ->first();

            $diskon_persen = 0;
            $diskon_nominal = 0;

            if ($diskon) {
                $diskon_persen = $diskon->nilai;
                $diskon_nominal = floor(($diskon_persen / 100) * $barang->harga_jual * $i['qty']);
            }

            $subtotal_sebelum_diskon = $barang->harga_jual * $i['qty'];
            $subtotal = $subtotal_sebelum_diskon - $diskon_nominal;

            $total_sebelum_diskon += $subtotal_sebelum_diskon;
            $total_diskon += $diskon_nominal;

            $detail[] = [
                'id' => $barang->id,
                'kode' => $barang->kode_barang,
                'nama' => $barang->nama_barang,
                'qty' => $i['qty'],
                'harga' => $barang->harga_jual,
                'diskon' => $diskon_persen,
                'diskon_nominal' => $diskon_nominal,
                'subtotal' => $subtotal
            ];
        }

        $grand_total = $total_sebelum_diskon - $total_diskon;

        if ($method === 'tunai') {
            return view('kasir.cekout-tunai', compact('total_sebelum_diskon', 'total_diskon', 'grand_total', 'detail'));
        }

        return view('kasir.cekout-online', compact('total_sebelum_diskon', 'total_diskon', 'grand_total', 'detail'));
    }
}