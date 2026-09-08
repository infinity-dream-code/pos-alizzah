<?php

namespace App\Http\Controllers;

use App\Models\Barang;
use Illuminate\Http\Request;

class BarangController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');

        $barangs = Barang::when($search, function ($query, $search) {
            return $query->where('kode_barang', 'like', "%{$search}%")
                ->orWhere('nama_barang', 'like', "%{$search}%");
        })
            ->paginate(20);

        return view('admin.barang.index', compact('barangs'));
    }

    public function create()
    {
        return view('admin.barang.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'kode_barang' => 'required|unique:barangs,kode_barang',
            'nama_barang' => 'required',
            'harga_beli' => 'required',
            'harga_jual' => 'required',
        ]);

        $harga_beli = str_replace('.', '', $request->harga_beli);
        $harga_jual = str_replace('.', '', $request->harga_jual);

        Barang::create([
            'kode_barang' => $request->kode_barang,
            'nama_barang' => $request->nama_barang,
            'deskripsi' => $request->deskripsi,
            'harga_beli' => $harga_beli,
            'harga_jual' => $harga_jual
        ]);

        return redirect()->route('barang.index')->with('success', 'Barang berhasil ditambahkan');
    }

    public function edit($id)
    {
        $barang = Barang::findOrFail($id);
        return view('admin.barang.edit', compact('barang'));
    }

    public function update(Request $request, $id)
    {
        $barang = Barang::findOrFail($id);

        $request->validate([
            'nama_barang' => 'required',
            'harga_beli' => 'required',
            'harga_jual' => 'required',
            'status' => 'required'
        ]);

        $harga_beli = str_replace('.', '', $request->harga_beli);
        $harga_jual = str_replace('.', '', $request->harga_jual);

        $barang->update([
            'nama_barang' => $request->nama_barang,
            'deskripsi' => $request->deskripsi,
            'harga_beli' => $harga_beli,
            'harga_jual' => $harga_jual,
            'status' => $request->status
        ]);

        return redirect()->route('barang.index')->with('success', 'Data barang berhasil diperbarui');
    }

    public function destroy($id)
    {
        $barang = Barang::findOrFail($id);
        $barang->delete();

        return redirect()->route('barang.index')->with('success', 'Barang berhasil dihapus');
    }
}
