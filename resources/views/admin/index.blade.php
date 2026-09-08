@extends('admin.template')

@section('content')

<div class="p-6">

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">

    <div class="bg-white rounded-xl shadow-md p-5 border border-slate-200">
        <h3 class="text-slate-600 font-medium text-sm uppercase tracking-wide">Total Penjualan</h3>
        <div class="text-3xl font-bold mt-2 text-slate-900">
            Rp {{ number_format($totalPenjualan, 0, ',', '.') }}
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-md p-5 border border-slate-200">
        <h3 class="text-slate-600 font-medium text-sm uppercase tracking-wide">Total Transaksi</h3>
        <div class="text-3xl font-bold mt-2 text-slate-900">
            {{ number_format($totalTransaksi, 0, ',', '.') }}
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-md p-5 border border-slate-200">
        <h3 class="text-slate-600 font-medium text-sm uppercase tracking-wide">Total Profit</h3>
        <div class="text-3xl font-bold mt-2 text-slate-900">
            Rp {{ number_format($totalProfit, 0, ',', '.') }}
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-md p-5 border border-slate-200">
        <h3 class="text-slate-600 font-medium text-sm uppercase tracking-wide">Profit Minggu Ini</h3>
        <div class="text-3xl font-bold mt-2 text-slate-900">
            Rp {{ number_format($profitMingguIni, 0, ',', '.') }}
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-md p-5 border border-slate-200">
        <h3 class="text-slate-600 font-medium text-sm uppercase tracking-wide">Jumlah Produk</h3>
        <div class="text-3xl font-bold mt-2 text-slate-900">
            {{ number_format($totalProduk, 0, ',', '.') }}
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-md p-5 border border-slate-200">
        <h3 class="text-slate-600 font-medium text-sm uppercase tracking-wide">Total Pelanggan</h3>
        <div class="text-3xl font-bold mt-2 text-slate-900">
            {{ number_format($totalPelanggan, 0, ',', '.') }}
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-md p-5 border border-slate-200">
        <h3 class="text-slate-600 font-medium text-sm uppercase tracking-wide">Total Diskon</h3>
        <div class="text-3xl font-bold mt-2 text-slate-900">
            Rp {{ number_format($totalDiskon, 0, ',', '.') }}
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-md p-5 border border-slate-200">
        <h3 class="text-slate-600 font-medium text-sm uppercase tracking-wide">Transaksi Minggu Ini</h3>
        <div class="text-3xl font-bold mt-2 text-slate-900">
            {{ number_format($transaksiMingguIni, 0, ',', '.') }}
        </div>
    </div>

</div>


    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-10">

        <div class="bg-white rounded-xl shadow-md border border-slate-200">
            <div class="p-5 border-b border-slate-200">
                <h3 class="text-lg font-bold text-slate-800">Transaksi Terbaru</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="p-3 text-left font-semibold text-slate-700">ID</th>
                            <th class="p-3 text-left font-semibold text-slate-700">Metode</th>
                            <th class="p-3 text-left font-semibold text-slate-700">Total</th>
                            <th class="p-3 text-left font-semibold text-slate-700">Profit</th>
                            <th class="p-3 text-left font-semibold text-slate-700">Tanggal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($transaksiTerbaru as $t)
                        <tr class="border-b hover:bg-slate-50">
                            <td class="p-3 text-indigo-600 font-semibold">{{ $t->kode_transaksi }}</td>
                            <td class="p-3">
                                @if($t->metode == 'tunai')
                                    <span class="px-3 py-1 text-xs text-white bg-green-500 rounded-lg">Tunai</span>
                                @else
                                    <span class="px-3 py-1 text-xs text-white bg-blue-500 rounded-lg">Online</span>
                                @endif
                            </td>
                            <td class="p-3 font-semibold">Rp {{ number_format($t->grand_total, 0, ',', '.') }}</td>
                            <td class="p-3 font-semibold text-green-600">Rp {{ number_format($t->profit, 0, ',', '.') }}</td>
                            <td class="p-3">{{ \Carbon\Carbon::parse($t->tanggal)->format('d M Y') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="space-y-6">

            <div class="bg-white rounded-xl shadow-md border border-slate-200">
                <div class="p-5 border-b border-slate-200">
                    <h3 class="text-lg font-bold text-slate-800">Top 3 Produk Paling Laku</h3>
                </div>
                <table class="w-full text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="p-3 text-left font-semibold text-slate-700">Produk</th>
                            <th class="p-3 text-right font-semibold text-slate-700">Terjual</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($topBarang as $b)
                        <tr class="border-b hover:bg-slate-50">
                            <td class="p-3">{{ $b->nama }}</td>
                            <td class="p-3 text-right font-semibold">{{ $b->total_terjual }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="bg-white rounded-xl shadow-md border border-slate-200">
                <div class="p-5 border-b border-slate-200">
                    <h3 class="text-lg font-bold text-slate-800">Top 3 Stok Menipis</h3>
                </div>
                <table class="w-full text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="p-3 text-left font-semibold text-slate-700">Produk</th>
                            <th class="p-3 text-right font-semibold text-slate-700">Stok</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($stokMenipis as $s)
                        <tr class="border-b hover:bg-slate-50">
                            <td class="p-3">{{ $s->nama_barang }}</td>
                            <td class="p-3 text-right font-semibold">{{ $s->stok }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

        </div>

    </div>

</div>

@endsection
