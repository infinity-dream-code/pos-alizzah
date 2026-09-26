@extends('admin.template')

@section('content')

<div class="content-area">

    <div class="flex justify-between items-center mb-6 flex-wrap gap-4">
        <h2 class="text-3xl font-bold text-slate-800">Master Supplier</h2>

        <a href="{{ route('supplier.create') }}"
           class="px-5 py-3 bg-indigo-600 text-white rounded-lg shadow hover:bg-indigo-700 transition font-semibold">
            + Tambah Supplier
        </a>
    </div>

    @if(session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4 font-semibold flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 fill-green-600" viewBox="0 0 24 24"><path d="M9 16.17 4.83 12l-1.42 1.41L9 19l12-12-1.41-1.41z"/></svg>
            {{ session('success') }}
        </div>
    @endif

    <div class="bg-white shadow-xl border border-slate-200 rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[800px] border-collapse">
                <thead>
                    <tr class="bg-gradient-to-r from-indigo-50 to-indigo-100 border-b border-slate-300">
                        <th class="py-4 px-4 text-left font-bold text-slate-700 text-sm">ID</th>
                        <th class="py-4 px-4 text-left font-bold text-slate-700 text-sm">Nama</th>
                        <th class="py-4 px-4 text-left font-bold text-slate-700 text-sm">Alamat</th>
                        <th class="py-4 px-4 text-left font-bold text-slate-700 text-sm">No. Telp</th>
                        <th class="py-4 px-4 text-center font-bold text-slate-700 text-sm">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($suppliers as $s)
                        <tr class="border-b hover:bg-slate-50 transition">
                            <td class="py-4 px-4 text-slate-600 font-semibold">{{ $s->id }}</td>
                            <td class="py-4 px-4 text-slate-800 font-semibold">{{ $s->nama }}</td>
                            <td class="py-4 px-4 text-slate-700">{{ $s->alamat ?: '-' }}</td>
                            <td class="py-4 px-4 text-slate-700">{{ $s->no_tlp ?: '-' }}</td>
                            <td class="py-4 px-4 text-center">
                                <div class="flex justify-center gap-2">
                                    <a href="{{ route('supplier.edit', $s->id) }}"
                                       class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-semibold shadow">
                                        Edit
                                    </a>
                                    <form action="{{ route('supplier.destroy', $s->id) }}" method="POST"
                                          onsubmit="return confirm('Yakin ingin menghapus supplier ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg text-sm font-semibold shadow">
                                            Hapus
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-10 text-center">
                                <h3 class="text-slate-500 font-semibold mb-1">Tidak Ada Data Supplier</h3>
                                <p class="text-slate-400 text-sm">Belum ada supplier yang ditambahkan</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($suppliers->hasPages())
        <div class="mt-6 flex justify-center">
            {{ $suppliers->links('pagination::tailwind') }}
        </div>
        <p class="text-center text-slate-500 text-sm mt-2">
            Menampilkan {{ $suppliers->firstItem() }} - {{ $suppliers->lastItem() }} dari {{ $suppliers->total() }} supplier
        </p>
    @endif

</div>

@endsection
