<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Barang;
use App\Models\Stok;
use Illuminate\Support\Facades\DB;

class StokController extends Controller
{
    public function index()
    {
        $barang = Barang::orderBy('id', 'desc')->get();
        return view('admin.stok.index', compact('barang'));
    }

    public function searchProducts(Request $request)
    {
        $keyword = $request->keyword;

        $products = Barang::where('nama_barang', 'like', "%{$keyword}%")
            ->orWhere('kode_barang', 'like', "%{$keyword}%")
            ->limit(10)
            ->get(['id', 'nama_barang', 'kode_barang', 'stok']);

        return response()->json($products);
    }

    public function store(Request $request)
    {
        $request->validate([
            'stok' => 'required|array',
            'stok.*' => 'required|integer|min:1'
        ]);

        try {
            DB::beginTransaction();

            foreach ($request->stok as $barangId => $tambahStok) {
                $barang = Barang::findOrFail($barangId);

                $barang->increment('stok', $tambahStok);

                Stok::create([
                    'id_barang' => $barangId,
                    'id_user' => auth()->id(),
                    'stok' => $tambahStok
                ]);
            }

            DB::commit();

            return redirect()->back()->with('success', 'Berhasil menambahkan stok.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Gagal: ' . $e->getMessage());
        }
    }
}
