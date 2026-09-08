<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Barang;
use App\Models\WaitingBarang;

class KasirController extends Controller
{
    public function index()
    {
        $waitingGroup = WaitingBarang::where('stok', '>', 0)
            ->with('barang')
            ->get()
            ->groupBy('barang_id');

        $waiting_barangs = WaitingBarang::where('stok', '>', 0)
            ->with('barang')
            ->get();

        $barangs = Barang::with('diskon')
            ->where('status', 'aktif')
            ->get();

        foreach ($barangs as $b) {
            $waitingStok = isset($waitingGroup[$b->id])
                ? $waitingGroup[$b->id]->sum('stok')
                : 0;

            $b->stok_total = $b->stok + $waitingStok;
        }

        return view('kasir.index', compact('barangs', 'waiting_barangs'));
    }
}
