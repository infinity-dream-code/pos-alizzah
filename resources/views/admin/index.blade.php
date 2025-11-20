@extends('admin.template')

@section('content')

<div class="p-6">

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">

        <div class="bg-white rounded-xl shadow-md p-5 border border-slate-200">
            <div class="flex items-center">
                <div class="w-12 h-12 flex items-center justify-center rounded-xl bg-blue-500">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-7 h-7 fill-white" viewBox="0 0 24 24">
                        <path d="M11.8 10.9c-2.27-.59-3-1.2-3-2.15 0-1.09 1.01-1.85 2.7-1.85 1.78 0 2.44.85 2.5 2.1h2.21c-.07-1.72-1.12-3.3-3.21-3.81V3h-3v2.16c-1.94.42-3.5 1.68-3.5 3.61 0 2.31 1.91 3.46 4.7 4.13 2.5.6 3 1.48 3 2.41 0 .69-.49 1.79-2.7 1.79-2.06 0-2.87-.92-2.98-2.1h-2.2c.12 2.19 1.76 3.42 3.68 3.83V21h3v-2.15c1.95-.37 3.5-1.5 3.5-3.55 0-2.84-2.43-3.81-4.7-4.4z"/>
                    </svg>
                </div>
            </div>

            <div class="mt-4">
                <h3 class="text-slate-700 font-semibold text-lg">Total Penjualan</h3>
                <div class="text-3xl font-bold mt-1 text-slate-900">Rp {{ number_format($totalPenjualan, 0, ',', '.') }}</div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-md p-5 border border-slate-200">
            <div class="flex items-center">
                <div class="w-12 h-12 flex items-center justify-center rounded-xl bg-green-500">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-7 h-7 fill-white" viewBox="0 0 24 24">
                        <path d="M7 18c-1.1 0-1.99.9-1.99 2S5.9 22 7 22s2-.9 2-2-.9-2-2-2zM1 2v2h2l3.6 7.59-1.35 2.45c-.16.28-.25.61-.25.96 0 1.1.9 2 2 2h12v-2H7.42c-.14 0-.25-.11-.25-.25l.03-.12.9-1.63h7.45c.75 0 1.41-.41 1.75-1.03l3.58-6.49c.08-.14.12-.31.12-.48 0-.55-.45-1-1-1H5.21l-.94-2H1zm16 16c-1.1 0-1.99.9-1.99 2s.89 2 1.99 2 2-.9 2-2-.9-2-2-2z"/>
                    </svg>
                </div>
            </div>

            <div class="mt-4">
                <h3 class="text-slate-700 font-semibold text-lg">Total Transaksi</h3>
                <div class="text-3xl font-bold mt-1 text-slate-900">{{ number_format($totalTransaksi, 0, ',', '.') }}</div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-md p-5 border border-slate-200">
            <div class="flex items-center">
                <div class="w-12 h-12 flex items-center justify-center rounded-xl bg-purple-500">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-7 h-7 fill-white" viewBox="0 0 24 24">
                        <path d="M20 6h-2.18c.11-.31.18-.65.18-1 0-1.66-1.34-3-3-3-1.05 0-1.96.54-2.5 1.35l-.5.67-.5-.68C10.96 2.54 10.05 2 9 2 7.34 2 6 3.34 6 5c0 .35.07.69.18 1H4c-1.11 0-1.99.89-1.99 2L2 19c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V8c0-1.11-.89-2-2-2zm-5-2c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1zM9 4c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1zm11 15H4v-2h16v2zm0-5H4V8h5.08L7 10.83 8.62 12 11 8.76l1-1.36 1 1.36L15.38 12 17 10.83 14.92 8H20v6z"/>
                    </svg>
                </div>
            </div>

            <div class="mt-4">
                <h3 class="text-slate-700 font-semibold text-lg">Total Produk</h3>
                <div class="text-3xl font-bold mt-1 text-slate-900">{{ number_format($totalProduk, 0, ',', '.') }}</div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-md p-5 border border-slate-200">
            <div class="flex items-center">
                <div class="w-12 h-12 flex items-center justify-center rounded-xl bg-orange-500">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-7 h-7 fill-white" viewBox="0 0 24 24">
                        <path d="M7 18c-1.1 0-1.99.9-1.99 2S5.9 22 7 22s2-.9 2-2-.9-2-2-2zM1 2v2h2l3.6 7.59-1.35 2.45c-.16.28-.25.61-.25.96 0 1.1.9 2 2 2h12v-2H7.42c-.14 0-.25-.11-.25-.25l.03-.12.9-1.63h7.45c.75 0 1.41-.41 1.75-1.03l3.58-6.49c.08-.14.12-.31.12-.48 0-.55-.45-1-1-1H5.21l-.94-2H1zm16 16c-1.1 0-1.99.9-1.99 2s.89 2 1.99 2 2-.9 2-2-.9-2-2-2z"/>
                    </svg>
                </div>
            </div>

            <div class="mt-4">
                <h3 class="text-slate-700 font-semibold text-lg">Total Profit Minggu Ini</h3>
                <div class="text-3xl font-bold mt-1 text-slate-900">Rp {{ number_format($profitMingguIni, 0, ',', '.') }}</div>
            </div>
        </div>

    </div>


    <div class="bg-white mt-10 rounded-xl shadow-md border border-slate-200">
        <div class="flex justify-between items-center p-5 border-b border-slate-200">
            <h3 class="text-lg font-bold text-slate-800">Transaksi Terbaru</h3>
            <a href="{{ route('transaksi.index') }}" class="text-indigo-600 font-semibold hover:underline">Lihat Semua</a>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="p-4 text-left font-semibold text-slate-700">ID Transaksi</th>
                        <th class="p-4 text-left font-semibold text-slate-700">Metode</th>
                        <th class="p-4 text-left font-semibold text-slate-700">Total</th>
                        <th class="p-4 text-left font-semibold text-slate-700">Profit</th>
                        <th class="p-4 text-left font-semibold text-slate-700">Tanggal</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($transaksiTerbaru as $t)
                    <tr class="border-b hover:bg-slate-50">
                        <td class="p-4 font-semibold text-indigo-600">{{ $t->kode_transaksi }}</td>

                        <td class="p-4">
                            @if($t->metode == 'tunai')
                                <span class="px-3 py-1 text-xs text-white bg-green-500 rounded-lg font-semibold">Tunai</span>
                            @else
                                <span class="px-3 py-1 text-xs text-white bg-blue-500 rounded-lg font-semibold">Online</span>
                            @endif
                        </td>

                        <td class="p-4 font-semibold">Rp {{ number_format($t->grand_total, 0, ',', '.') }}</td>

                        <td class="p-4 font-semibold text-green-600">
                            Rp {{ number_format($t->profit, 0, ',', '.') }}
                        </td>

                        <td class="p-4">{{ \Carbon\Carbon::parse($t->tanggal)->format('d M Y') }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="p-8 text-center text-slate-500">Tidak ada transaksi</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>

@endsection
