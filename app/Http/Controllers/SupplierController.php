<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index()
    {
        $suppliers = Supplier::orderBy('nama')->paginate(10);
        return view('admin.supplier.index', compact('suppliers'));
    }

    public function create()
    {
        return view('admin.supplier.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'nama' => 'required|string|max:150',
            'alamat' => 'nullable|string',
            'no_tlp' => 'nullable|string|max:30',
        ]);

        Supplier::create([
            'nama' => $request->nama,
            'alamat' => $request->alamat,
            'no_tlp' => $request->no_tlp,
        ]);

        return redirect()->route('supplier.index')->with('success', 'Supplier berhasil ditambahkan');
    }

    public function edit($id)
    {
        $supplier = Supplier::findOrFail($id);
        return view('admin.supplier.edit', compact('supplier'));
    }

    public function update(Request $request, $id)
    {
        $supplier = Supplier::findOrFail($id);

        $request->validate([
            'nama' => 'required|string|max:150',
            'alamat' => 'nullable|string',
            'no_tlp' => 'nullable|string|max:30',
        ]);

        $supplier->update([
            'nama' => $request->nama,
            'alamat' => $request->alamat,
            'no_tlp' => $request->no_tlp,
        ]);

        return redirect()->route('supplier.index')->with('success', 'Supplier berhasil diperbarui');
    }

    public function destroy($id)
    {
        $supplier = Supplier::findOrFail($id);
        $supplier->delete();

        return redirect()->route('supplier.index')->with('success', 'Supplier berhasil dihapus');
    }
}
