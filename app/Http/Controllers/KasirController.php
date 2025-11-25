<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Barang;
class KasirController extends Controller
{
    public function index()
{
    $barangs = Barang::with('diskon')
        ->where('stok', '>', 0)
        ->where('status', 'aktif')
        ->get();

    return view('kasir.index', compact('barangs'));
}

}
