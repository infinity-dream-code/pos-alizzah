<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Barang;
use App\Models\Transaksi;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    public function index()
    {
        $totalProduk = Barang::count();
        $totalPenjualan = Transaksi::sum('grand_total');
        $totalTransaksi = Transaksi::count();
        $totalDiskon = Transaksi::sum('diskon_nominal');
        $totalPelanggan = Transaksi::whereNotNull('pelanggan_id')->distinct('pelanggan_id')->count('pelanggan_id');
        $totalProfit = Transaksi::sum('profit');

        $startOfWeek = Carbon::now()->startOfWeek();
        $endOfWeek = Carbon::now()->endOfWeek();

        $transaksiMingguIni = Transaksi::whereBetween('tanggal', [$startOfWeek, $endOfWeek])->count();
        $profitMingguIni = Transaksi::whereBetween('tanggal', [$startOfWeek, $endOfWeek])->sum('profit');

        $transaksiTerbaru = Transaksi::with('user')
            ->orderBy('id', 'desc')
            ->limit(10)
            ->get();

        $topBarang = DB::table('detail_transaksi')
            ->join('barangs', 'detail_transaksi.barang_id', '=', 'barangs.id')
            ->select('barangs.nama_barang as nama', DB::raw('SUM(detail_transaksi.qty) as total_terjual'))
            ->groupBy('barangs.nama_barang')
            ->orderBy('total_terjual', 'desc')
            ->limit(3)
            ->get();

        $stokMenipis = Barang::orderBy('stok', 'asc')
            ->limit(3)
            ->get();

        return view('admin.index', compact(
            'totalProduk',
            'totalPenjualan',
            'totalTransaksi',
            'totalDiskon',
            'totalPelanggan',
            'totalProfit',
            'profitMingguIni',
            'transaksiMingguIni',
            'transaksiTerbaru',
            'topBarang',
            'stokMenipis'
        ));
    }
}
