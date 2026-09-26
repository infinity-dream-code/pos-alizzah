@extends('admin.template')

@section('content')

<div class="content-area">

    <h2 class="text-3xl font-bold text-slate-800 mb-6">Edit Supplier</h2>

    <div class="bg-white shadow-xl border border-slate-200 rounded-xl p-8">
        <form action="{{ route('supplier.update', $supplier->id) }}" method="POST" class="space-y-6">
            @csrf
            @method('PUT')

            <div>
                <label class="font-semibold text-slate-700 mb-1 block">Nama</label>
                <input type="text" name="nama" value="{{ old('nama', $supplier->nama) }}" required
                       class="w-full px-4 py-3 border border-slate-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
                @error('nama')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="font-semibold text-slate-700 mb-1 block">Alamat</label>
                <textarea name="alamat" rows="3"
                          class="w-full px-4 py-3 border border-slate-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500">{{ old('alamat', $supplier->alamat) }}</textarea>
            </div>

            <div>
                <label class="font-semibold text-slate-700 mb-1 block">No. Telp</label>
                <input type="text" name="no_tlp" value="{{ old('no_tlp', $supplier->no_tlp) }}"
                       class="w-full px-4 py-3 border border-slate-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
            </div>

            <div class="flex gap-4 pt-4">
                <button type="submit"
                        class="px-7 py-3 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-semibold shadow-lg">
                    Update
                </button>
                <a href="{{ route('supplier.index') }}"
                   class="px-7 py-3 bg-slate-300 text-slate-800 hover:bg-slate-400 rounded-lg font-semibold">
                    Batal
                </a>
            </div>
        </form>
    </div>

</div>

@endsection
