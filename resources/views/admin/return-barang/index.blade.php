@extends('admin.template')

@section('content')

<div class="px-6 py-4">

    <h2 class="text-2xl font-semibold mb-4">Return Barang</h2>
    <p class="text-slate-500 text-sm mb-4">Return barang akan mengurangi stok barang yang tersedia.</p>

    @if(session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4 font-semibold">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4 font-semibold">
            {{ session('error') }}
        </div>
    @endif

    @if($suppliers->isEmpty())
        <div class="bg-yellow-50 border border-yellow-300 text-yellow-800 px-4 py-3 rounded mb-4">
            Belum ada data supplier. Silakan <a href="{{ route('supplier.create') }}" class="underline font-semibold">tambah supplier</a> terlebih dahulu.
        </div>
    @else
        <div class="bg-white shadow rounded p-4 mb-6">
            <label class="block text-sm font-semibold text-slate-700 mb-2">Cari Barang</label>
            <input type="text" id="scan" class="w-full border border-gray-300 rounded px-3 py-2 focus:ring focus:ring-blue-300" placeholder="Scan atau ketik kode / nama barang">
            <div id="result" class="mt-3 space-y-3"></div>
        </div>
    @endif

    <h3 class="text-xl font-semibold mb-2">Riwayat Return Barang</h3>

    <div class="bg-white shadow rounded p-4 overflow-x-auto">
        <table class="w-full text-left">
            <thead>
                <tr class="border-b">
                    <th class="py-2">ID</th>
                    <th class="py-2">Kode</th>
                    <th class="py-2">Nama Barang</th>
                    <th class="py-2">Supplier</th>
                    <th class="py-2">Stok Keluar</th>
                    <th class="py-2">Tanggal</th>
                </tr>
            </thead>
            <tbody>
                @forelse($returns as $r)
                <tr class="border-b">
                    <td class="py-2">{{ $r->id }}</td>
                    <td class="py-2">{{ $r->barang->kode_barang ?? '-' }}</td>
                    <td class="py-2">{{ $r->barang->nama_barang ?? '-' }}</td>
                    <td class="py-2">{{ $r->supplier->nama ?? '-' }}</td>
                    <td class="py-2 font-semibold text-red-600">-{{ $r->total_stok }}</td>
                    <td class="py-2">{{ $r->created_at->timezone('Asia/Jakarta')->format('d/m/Y H:i') }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="py-8 text-center text-slate-500">Belum ada data return barang</td>
                </tr>
                @endforelse
            </tbody>
        </table>

        @if ($returns->hasPages())
            <div class="mt-4 flex justify-center">
                {{ $returns->links('pagination::tailwind') }}
            </div>
        @endif

        @if ($returns->total() > 0)
            <p class="text-center text-slate-500 text-sm mt-2">
                Menampilkan {{ $returns->firstItem() }} - {{ $returns->lastItem() }} dari {{ $returns->total() }} data
            </p>
        @endif
    </div>
</div>

@if(!$suppliers->isEmpty())
<script>
const scan = document.getElementById('scan');
const result = document.getElementById('result');
const suppliers = @json($suppliers);

scan.addEventListener('keyup', function () {
    let q = this.value;
    if (q.length < 2) {
        result.innerHTML = '';
        return;
    }

    fetch(`/admin/return-barang/search-products?q=${encodeURIComponent(q)}`)
        .then(res => res.json())
        .then(data => {
            let html = '';

            if (!data.length) {
                result.innerHTML = '<div class="text-slate-500 text-sm">Barang tidak ditemukan atau stok habis.</div>';
                return;
            }

            data.forEach(item => {
                let supplierOptions = suppliers.map(s =>
                    `<option value="${s.id}">${s.nama}</option>`
                ).join('');

                html += `
                <form method="POST" action="{{ route('return.store') }}" class="bg-white shadow rounded p-4 space-y-3 border border-slate-100"
                      onsubmit="return confirm('Return barang ini akan mengurangi stok. Lanjutkan?')">
                    @csrf
                    <input type="hidden" name="barang_id" value="${item.id}">

                    <div class="text-lg font-semibold">${item.nama_barang}</div>
                    <div class="text-gray-600 text-sm">Kode: ${item.kode_barang} · Stok tersedia: <span class="font-semibold text-indigo-600">${item.stok}</span></div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-3">
                        <div>
                            <label class="text-xs text-gray-500 mb-1 block">Jumlah keluar</label>
                            <input type="number" name="total_stok" value="1" min="1" max="${item.stok}" required
                                class="border border-gray-300 rounded px-3 h-11 w-full">
                        </div>
                        <div>
                            <label class="text-xs text-gray-500 mb-1 block">Supplier</label>
                            <select name="supplier_id" required
                                class="border border-gray-300 rounded px-3 h-11 w-full bg-white">
                                <option value="">Pilih supplier</option>
                                ${supplierOptions}
                            </select>
                        </div>
                        <div class="flex items-end">
                            <button class="bg-red-600 text-white rounded px-6 h-11 w-full hover:bg-red-700 transition">
                                Return (Kurangi Stok)
                            </button>
                        </div>
                    </div>
                </form>
                `;
            });

            result.innerHTML = html;
        });
});
</script>
@endif

@endsection
