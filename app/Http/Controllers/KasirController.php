<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Barang;
class KasirController extends Controller
{
    public function index()
    {
        $barangs = Barang::where('status', 'aktif')->paginate(12);
        return view('kasir.index', compact('barangs'));
    }
}
