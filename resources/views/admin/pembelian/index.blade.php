@extends('admin.template')

@section('content')

<div class="px-6 py-4">

    <h2 class="text-2xl font-semibold mb-4">Tambah Barang Pembelian</h2>

    <div class="bg-white shadow rounded p-4 mb-6">
        <input type="text" id="scan" class="w-full border border-gray-300 rounded px-3 py-2 focus:ring focus:ring-blue-300" placeholder="Scan atau ketik kode barang">
        <div id="result" class="mt-3 space-y-3"></div>
    </div>

    <h3 class="text-xl font-semibold mb-2">Daftar Waiting Barang</h3>

    <div class="bg-white shadow rounded p-4 overflow-x-auto">
        <table class="w-full text-left">
            <thead>
                <tr class="border-b">
                    <th class="py-2">Kode</th>
                    <th class="py-2">Nama</th>
                    <th class="py-2">Stok</th>
                    <th class="py-2">Harga Beli</th>
                    <th class="py-2">Harga Jual</th>
                </tr>
            </thead>
            <tbody>
                @foreach($waiting as $w)
                <tr class="border-b">
                    <td class="py-2">{{ $w->kode_barang }}</td>
                    <td class="py-2">{{ $w->barang->nama_barang }}</td>
                    <td class="py-2">{{ $w->stok }}</td>
                    <td class="py-2">Rp {{ number_format($w->harga_beli, 0, ',', '.') }}</td>
                    <td class="py-2">Rp {{ number_format($w->harga_jual, 0, ',', '.') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div class="mt-4">
            {{ $waiting->links('pagination::tailwind') }}
        </div>
    </div>

</div>

<script>
const scan = document.getElementById('scan');
const result = document.getElementById('result');

scan.addEventListener('keyup', function () {
    let q = this.value;
    if (q.length < 2) {
        result.innerHTML = '';
        return;
    }

    fetch(`/admin/pembelian/search-products?q=${q}`)
        .then(res => res.json())
        .then(data => {
            let html = '';
            data.forEach(item => {
                html += `
                <form method="POST" action="{{ route('waiting.store') }}" class="formTambah bg-white shadow rounded p-4 space-y-3">
                    @csrf
                    <input type="hidden" name="barang_id" value="${item.id}">
                    
                    <div class="text-lg font-semibold">${item.nama_barang}</div>
                    <div class="text-gray-600 text-sm">Kode: ${item.kode_barang}</div>

                    <div class="grid grid-cols-4 gap-4 mt-3">

                        <div>
                            <input type="number" name="stok" value="1" min="1"
                                class="border border-gray-300 rounded px-3 h-11 w-full">
                            <div class="text-xs text-gray-500 mt-1">Jumlah masuk</div>
                        </div>

                        <div>
                            <input type="text" name="harga_beli" placeholder="Harga beli"
                                class="uang border border-gray-300 rounded px-3 h-11 w-full">
                            <div class="text-xs text-gray-500 mt-1">Default bila kosong</div>
                        </div>

                        <div>
                            <input type="text" name="harga_jual" placeholder="Harga jual"
                                class="uang border border-gray-300 rounded px-3 h-11 w-full">
                            <div class="text-xs text-gray-500 mt-1">Default bila kosong</div>
                        </div>

                        <div class="flex items-start h-11">
                            <button class="bg-blue-600 text-white rounded px-6 h-11 w-full hover:bg-blue-700 transition">
                                Tambah
                            </button>
                        </div>

                    </div>
                </form>
                `;
            });

            result.innerHTML = html;
            applyFormat();
        });
});

function applyFormat() {
    document.querySelectorAll('.uang').forEach(input => {
        input.addEventListener('input', function () {
            let v = this.value.replace(/[^\d]/g, '');
            this.value = v.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        });
    });

    document.querySelectorAll('.formTambah').forEach(f => {
        f.addEventListener('submit', function () {
            let beli = this.querySelector('input[name="harga_beli"]');
            let jual = this.querySelector('input[name="harga_jual"]');
            if (beli) beli.value = beli.value.replace(/\./g, '');
            if (jual) jual.value = jual.value.replace(/\./g, '');
        });
    });
}
</script>

@endsection
