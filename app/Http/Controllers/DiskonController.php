<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Barang;
use App\Models\Diskon;

class DiskonController extends Controller
{
    public function index()
    {
        $diskons = Diskon::with('barang')->orderBy('id', 'desc')->paginate(10);
        return view('admin.diskon.index', compact('diskons'));
    }

    public function searchProducts(Request $request)
    {
        $keyword = $request->keyword;

        $products = Barang::where('nama_barang', 'like', "%{$keyword}%")
            ->orWhere('kode_barang', 'like', "%{$keyword}%")
            ->limit(10)
            ->get(['id', 'nama_barang', 'kode_barang']);

        return response()->json($products);
    }

    public function store(Request $request)
    {
        $request->validate([
            'barang_ids' => 'required',
            'nilai' => 'required|numeric|min:0',
            'aktif' => 'required|in:0,1'
        ]);

        $barangIds = json_decode($request->barang_ids);

        if (empty($barangIds)) {
            return redirect()->back()->with('error', 'Pilih minimal 1 produk');
        }

        foreach ($barangIds as $barangId) {
            Diskon::create([
                'barang_id' => $barangId,
                'nilai' => $request->nilai,
                'aktif' => $request->aktif
            ]);
        }

        return redirect()->back()->with('success', 'Berhasil menambahkan diskon untuk ' . count($barangIds) . ' produk');
    }
    public function update(Request $request, $id)
    {
        $diskon = Diskon::findOrFail($id);

        $request->validate([
            'nilai' => 'required|numeric|min:0',
            'aktif' => 'required|in:0,1'
        ]);

        $diskon->update([
            'nilai' => $request->nilai,
            'aktif' => $request->aktif
        ]);

        return redirect()->back()->with('success', 'Diskon berhasil diupdate');
    }

    public function destroy($id)
    {
        $diskon = Diskon::findOrFail($id);
        $diskon->delete();

        return response()->json(['success' => true]);
    }

    public function toggleStatus($id)
    {
        $diskon = Diskon::findOrFail($id);
        $diskon->update(['aktif' => request('aktif')]);

        return response()->json(['success' => true]);
    }

    public function updateMass(Request $request)
    {
        $ids = json_decode($request->ids);

        Diskon::whereIn('id', $ids)->update([
            'nilai' => $request->nilai,
            'aktif' => $request->aktif
        ]);

        return response()->json(['success' => true]);
    }

    public function deleteMass(Request $request)
    {
        $ids = json_decode($request->ids);
        Diskon::whereIn('id', $ids)->delete();

        return response()->json(['success' => true]);
    }
}
