<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Barang;
use App\Models\Transaksi;
use Carbon\Carbon;

class AdminController extends Controller
{
    public function index()
    {
        $totalProduk = Barang::count();
        $totalPenjualan = Transaksi::sum('grand_total');
        $totalTransaksi = Transaksi::count();

        $startOfWeek = Carbon::now()->startOfWeek();
        $endOfWeek = Carbon::now()->endOfWeek();

        $profitMingguIni = Transaksi::whereBetween('tanggal', [$startOfWeek, $endOfWeek])
            ->sum('profit');

        $transaksiTerbaru = Transaksi::with('user')
            ->orderBy('id', 'desc')
            ->limit(5)
            ->get();

        return view('admin.index', compact(
            'totalProduk',
            'totalPenjualan',
            'totalTransaksi',
            'profitMingguIni',
            'transaksiTerbaru'
        ));
    }
}
