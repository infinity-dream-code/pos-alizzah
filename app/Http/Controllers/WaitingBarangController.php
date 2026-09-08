<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\WaitingBarang;
use App\Models\Barang;

class WaitingBarangController extends Controller
{
    public function index()
    {
        $waiting = WaitingBarang::with('barang')->orderBy('id', 'desc')->paginate(10);
        return view('admin.pembelian.index', compact('waiting'));
    }

    public function searchProducts(Request $request)
    {
        $q = $request->q;
        $barang = Barang::where('kode_barang', 'like', "%$q%")
            ->orWhere('nama_barang', 'like', "%$q%")
            ->limit(10)
            ->get();
        return response()->json($barang);
    }

    public function store(Request $request)
    {
        $barang = Barang::findOrFail($request->barang_id);

        $hb = $request->harga_beli ? str_replace('.', '', $request->harga_beli) : $barang->harga_beli;
        $hj = $request->harga_jual ? str_replace('.', '', $request->harga_jual) : $barang->harga_jual;

        WaitingBarang::create([
            'barang_id' => $barang->id,
            'kode_barang' => $barang->kode_barang,
            'stok' => $request->stok,
            'harga_beli' => $hb,
            'harga_jual' => $hj
        ]);

        return redirect()->route('waiting.index');
    }
}
