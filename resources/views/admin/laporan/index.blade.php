@extends('admin.template')

@section('content')

<div class="p-6">

    <h2 class="text-3xl font-bold text-slate-800 mb-6">Laporan Transaksi</h2>

    {{-- Filter Section --}}
    <div class="bg-white shadow-md rounded-xl p-6 border mb-6">
        <form action="{{ route('laporan.search') }}" method="GET">

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">

                <div>
                    <label class="font-semibold text-slate-700">Tanggal Dari</label>
                    <input type="date" name="tanggal_dari" value="{{ request('tanggal_dari') }}"
                           class="input w-full">
                </div>

                <div>
                    <label class="font-semibold text-slate-700">Tanggal Sampai</label>
                    <input type="date" name="tanggal_sampai" value="{{ request('tanggal_sampai') }}"
                           class="input w-full">
                </div>

                <div>
                    <label class="font-semibold text-slate-700">Metode Pembayaran</label>
                    <select name="metode" class="input w-full bg-white">
                        <option value="">Semua</option>
                        <option value="tunai" {{ request('metode')=='tunai'?'selected':'' }}>Tunai</option>
                        <option value="online" {{ request('metode')=='online'?'selected':'' }}>Online</option>
                        <option value="face" {{ request('metode')=='face'?'selected':'' }}>FacePay</option>
                    </select>
                </div>

                <div class="flex items-end gap-3">
                    <button class="px-6 py-3 bg-indigo-600 text-white rounded-lg font-semibold hover:bg-indigo-700">
                        Filter
                    </button>

                    <a href="{{ route('laporan.cetak.pdf', request()->all()) }}"
                       class="px-6 py-3 bg-green-600 text-white rounded-lg font-semibold hover:bg-green-700">
                        Cetak PDF
                    </a>
                </div>
            </div>

        </form>
    </div>

    {{-- Summary --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">

        <div class="bg-white p-5 rounded-xl shadow border">
            <p class="text-slate-500 text-sm">Total Transaksi</p>
            <h3 class="text-2xl font-bold">{{ $transaksis->count() }}</h3>
        </div>

        <div class="bg-white p-5 rounded-xl shadow border">
            <p class="text-slate-500 text-sm">Total Pendapatan</p>
            <h3 class="text-2xl font-bold text-green-600">
                Rp {{ number_format($transaksis->sum('grand_total'),0,',','.') }}
            </h3>
        </div>

        <div class="bg-white p-5 rounded-xl shadow border">
            <p class="text-slate-500 text-sm">Transaksi Tunai</p>
            <h3 class="text-xl font-bold">{{ $transaksis->where('metode','tunai')->count() }}</h3>
        </div>

        <div class="bg-white p-5 rounded-xl shadow border">
            <p class="text-slate-500 text-sm">Transaksi Online</p>
            <h3 class="text-xl font-bold">{{ $transaksis->where('metode','online')->count() }}</h3>
        </div>

    </div>

    {{-- Table --}}
    <div class="bg-white shadow-md rounded-xl overflow-hidden border">
        <table class="w-full border-collapse">
            <thead>
                <tr class="bg-slate-100 text-slate-700">
                    <th class="p-3 text-left font-semibold">No</th>
                    <th class="p-3 text-left font-semibold">Tanggal</th>
                    <th class="p-3 text-left font-semibold">Kode</th>
                    <th class="p-3 text-left font-semibold">Kasir</th>
                    <th class="p-3 text-center font-semibold">Metode</th>
                    <th class="p-3 text-right font-semibold">Total</th>
                    <th class="p-3 text-right font-semibold">Grand Total</th>
                </tr>
            </thead>

            <tbody>
                @forelse($transaksis as $t)
                <tr class="border-b hover:bg-slate-50">
                    <td class="p-3">{{ $transaksis->firstItem() + $loop->index }}</td>
                    <td class="p-3">{{ \Carbon\Carbon::parse($t->tanggal)->format('d M Y H:i') }}</td>
                    <td class="p-3 font-semibold text-indigo-600">{{ $t->kode_transaksi }}</td>
                    <td class="p-3">{{ $t->user->nama ?? '-' }}</td>
                    <td class="p-3 text-center">
                        @if($t->metode == 'tunai')
                            <span class="px-3 py-1 text-xs bg-green-500 text-white rounded">Tunai</span>
                        @else
                            <span class="px-3 py-1 text-xs bg-blue-500 text-white rounded">Online</span>
                        @endif
                    </td>
                    <td class="p-3 text-right">Rp {{ number_format($t->total,0,',','.') }}</td>
                    <td class="p-3 text-right font-bold">Rp {{ number_format($t->grand_total,0,',','.') }}</td>
                </tr>
                @empty

                <tr>
                    <td colspan="7" class="p-10 text-center text-slate-500 font-semibold">
                        Tidak ada data laporan
                    </td>
                </tr>

                @endforelse
            </tbody>

        </table>
    </div>
    <div class="mt-6">
    {{ $transaksis->appends(request()->query())->links('pagination::tailwind') }}
</div>

</div>

@endsection
