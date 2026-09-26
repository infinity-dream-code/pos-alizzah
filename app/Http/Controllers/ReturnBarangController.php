<?php

namespace App\Http\Controllers;

use App\Models\Barang;
use App\Models\ReturnBarang;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReturnBarangController extends Controller
{
    public function index()
    {
        $returns = ReturnBarang::with(['barang', 'supplier'])
            ->orderBy('id', 'desc')
            ->paginate(10);
        $suppliers = Supplier::orderBy('nama')->get();

        return view('admin.return-barang.index', compact('returns', 'suppliers'));
    }

    public function searchProducts(Request $request)
    {
        $q = $request->q;

        $barang = Barang::where(function ($query) use ($q) {
                $query->where('kode_barang', 'like', "%{$q}%")
                    ->orWhere('nama_barang', 'like', "%{$q}%");
            })
            ->where('stok', '>', 0)
            ->limit(10)
            ->get(['id', 'kode_barang', 'nama_barang', 'stok']);

        return response()->json($barang);
    }

    public function store(Request $request)
    {
        $request->validate([
            'barang_id' => 'required|exists:barangs,id',
            'supplier_id' => 'required|exists:suppliers,id',
            'total_stok' => 'required|integer|min:1',
        ]);

        try {
            DB::beginTransaction();

            $barang = Barang::lockForUpdate()->findOrFail($request->barang_id);

            if ($request->total_stok > $barang->stok) {
                DB::rollBack();
                return redirect()->back()->with('error', 'Stok tidak cukup. Stok tersedia: ' . $barang->stok);
            }

            $barang->decrement('stok', $request->total_stok);

            ReturnBarang::create([
                'barang_id' => $barang->id,
                'supplier_id' => $request->supplier_id,
                'user_id' => Auth::id(),
                'total_stok' => $request->total_stok,
            ]);

            DB::commit();

            return redirect()->route('return.index')->with('success', 'Return barang berhasil. Stok dikurangi ' . $request->total_stok);
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Gagal: ' . $e->getMessage());
        }
    }
}
