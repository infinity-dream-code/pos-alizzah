@extends('admin.template')

@section('content')

<div class="p-6">

    <h2 class="text-3xl font-bold text-slate-800 mb-6">Edit Barang</h2>

    <div class="bg-white shadow-xl rounded-xl p-8 border border-slate-200">

        <form action="{{ url('admin/barang/update/'.$barang->id) }}" method="POST" class="space-y-8">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">

                <div>
                    <label class="block font-semibold mb-1 text-slate-700">Plu</label>
                    <input type="text" name="kode_barang" value="{{ $barang->kode_barang }}" readonly
                           class="w-full px-4 py-3 border rounded-lg bg-slate-100 text-slate-500 border-slate-300">
                </div>

                <div>
                    <label class="block font-semibold mb-1 text-slate-700">Nama Barang</label>
                    <input type="text" name="nama_barang" value="{{ $barang->nama_barang }}"
                           class="w-full px-4 py-3 border rounded-lg border-slate-300 focus:ring-indigo-500 focus:border-indigo-500">
                </div>

                <div>
                    <label class="block font-semibold mb-1 text-slate-700">Status</label>
                    <select name="status"
                            class="w-full px-4 py-3 border rounded-lg bg-white border-slate-300 focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="aktif" {{ $barang->status == 'aktif' ? 'selected' : '' }}>Aktif</option>
                        <option value="nonaktif" {{ $barang->status == 'nonaktif' ? 'selected' : '' }}>Nonaktif</option>
                    </select>
                </div>

                <div>
                    <label class="block font-semibold mb-1 text-slate-700">Harga Beli</label>
                    <input type="text" id="harga_beli" name="harga_beli"
                           value="{{ number_format($barang->harga_beli, 0, ',', '.') }}"
                           class="w-full px-4 py-3 border rounded-lg border-slate-300 focus:ring-indigo-500 focus:border-indigo-500">
                </div>

                <div>
                    <label class="block font-semibold mb-1 text-slate-700">Harga Jual</label>
                    <input type="text" id="harga_jual" name="harga_jual"
                           value="{{ number_format($barang->harga_jual, 0, ',', '.') }}"
                           class="w-full px-4 py-3 border rounded-lg border-slate-300 focus:ring-indigo-500 focus:border-indigo-500">
                </div>

            </div>

            <div>
                <label class="block font-semibold mb-1 text-slate-700">Deskripsi</label>
                <textarea name="deskripsi" rows="4"
                          class="w-full px-4 py-3 border rounded-lg border-slate-300 focus:ring-indigo-500 focus:border-indigo-500">{{ $barang->deskripsi }}</textarea>
            </div>

            <div class="flex gap-4 pt-4">
                <button type="submit"
                        class="px-7 py-3 bg-indigo-600 text-white rounded-lg font-semibold shadow-lg hover:bg-indigo-700 transition">
                    Update
                </button>

                <a href="{{ route('barang.index') }}"
                   class="px-7 py-3 bg-slate-300 text-slate-800 rounded-lg font-semibold hover:bg-slate-400 transition">
                    Batal
                </a>
            </div>

        </form>
    </div>

</div>

<script>
function formatRupiah(v) {
    return v.replace(/\D/g, '').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}
document.getElementById('harga_beli').addEventListener('input', function() {
    this.value = formatRupiah(this.value);
});
document.getElementById('harga_jual').addEventListener('input', function() {
    this.value = formatRupiah(this.value);
});
</script>

@endsection
