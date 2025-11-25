@extends('admin.template')

@section('content')

<div class="p-6">

    <h2 class="text-3xl font-bold text-slate-800 mb-6">Tambah Barang</h2>

    <div class="bg-white shadow-xl rounded-xl p-8 border border-slate-200">

        <form action="{{ route('barang.store') }}" method="POST" class="space-y-8">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">

                <div>
                    <label class="block font-semibold mb-1 text-slate-700">PLU</label>
                    <input type="text" name="kode_barang"
                           value="{{ old('kode_barang') }}"
                           class="w-full px-4 py-3 border rounded-lg border-slate-300 focus:ring-indigo-500 focus:border-indigo-500">
                    @error('kode_barang')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block font-semibold mb-1 text-slate-700">Nama Barang</label>
                    <input type="text" name="nama_barang"
                           value="{{ old('nama_barang') }}"
                           class="w-full px-4 py-3 border rounded-lg border-slate-300 focus:ring-indigo-500 focus:border-indigo-500">
                    @error('nama_barang')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block font-semibold mb-1 text-slate-700">Harga Beli</label>
                    <input type="text" id="harga_beli" name="harga_beli"
                           value="{{ old('harga_beli') }}"
                           class="w-full px-4 py-3 border rounded-lg border-slate-300 focus:ring-indigo-500 focus:border-indigo-500"
                           placeholder="0">
                    @error('harga_beli')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block font-semibold mb-1 text-slate-700">Harga Jual</label>
                    <input type="text" id="harga_jual" name="harga_jual"
                           value="{{ old('harga_jual') }}"
                           class="w-full px-4 py-3 border rounded-lg border-slate-300 focus:ring-indigo-500 focus:border-indigo-500"
                           placeholder="0">
                    @error('harga_jual')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

            </div>

            <div>
                <label class="block font-semibold mb-1 text-slate-700">Deskripsi</label>
                <textarea name="deskripsi" rows="4"
                          class="w-full px-4 py-3 border rounded-lg border-slate-300 focus:ring-indigo-500 focus:border-indigo-500"
                          placeholder="Tulis deskripsi barang...">{{ old('deskripsi') }}</textarea>
            </div>

            <div class="flex gap-4 pt-4">
                <button type="submit"
                    class="px-7 py-3 bg-indigo-600 text-white rounded-lg font-semibold shadow-lg hover:bg-indigo-700 transition">
                    Simpan
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
document.querySelector("input[name='kode_barang']").addEventListener("keydown", function(e) {
    if (e.key === "Enter") e.preventDefault();
});
function formatRupiah(value) {
    return value.replace(/\D/g, '').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}
document.getElementById('harga_beli').addEventListener('input', function() {
    this.value = formatRupiah(this.value);
});
document.getElementById('harga_jual').addEventListener('input', function() {
    this.value = formatRupiah(this.value);
});
</script>

@endsection
