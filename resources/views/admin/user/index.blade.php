@extends('admin.template')

@section('content')

<div class="content-area">

    <div class="flex justify-between items-center mb-6 flex-wrap gap-4">
        <h2 class="text-3xl font-bold text-slate-800">Daftar User</h2>

        <a href="{{ route('user.create') }}"
           class="px-5 py-3 bg-indigo-600 text-white rounded-lg shadow hover:bg-indigo-700 transition font-semibold">
            + Tambah User
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
                        <th class="py-4 px-4 text-left font-bold text-slate-700 text-sm">No</th>
                        <th class="py-4 px-4 text-left font-bold text-slate-700 text-sm">Nama</th>
                        <th class="py-4 px-4 text-left font-bold text-slate-700 text-sm">Username</th>
                        <th class="py-4 px-4 text-left font-bold text-slate-700 text-sm">Role</th>
                        <th class="py-4 px-4 text-center font-bold text-slate-700 text-sm">Aksi</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($users as $index => $u)
                        <tr class="border-b hover:bg-slate-50 transition">
                            <td class="py-4 px-4 text-slate-600 font-semibold">
                                {{ $users->firstItem() + $index }}
                            </td>
                            <td class="py-4 px-4 text-slate-800 font-semibold">
                                {{ $u->nama }}
                            </td>
                            <td class="py-4 px-4 text-slate-700">
                                {{ $u->username }}
                            </td>
                            <td class="py-4 px-4">
                                @if($u->role == 'admin')
                                    <span class="px-3 py-1 rounded-lg text-white bg-indigo-600 text-xs font-semibold">
                                        Admin
                                    </span>
                                @else
                                    <span class="px-3 py-1 rounded-lg text-white bg-green-600 text-xs font-semibold">
                                        Kasir
                                    </span>
                                @endif
                            </td>
                            <td class="py-4 px-4 text-center">
                                <div class="flex justify-center gap-2">
                                    <a href="{{ route('user.edit', $u->id) }}"
                                       class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-semibold shadow">
                                        Edit
                                    </a>

                                    <form action="{{ route('user.destroy', $u->id) }}" method="POST"
                                          onsubmit="return confirm('Yakin ingin menghapus user ini?')">
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
                                <h3 class="text-slate-500 font-semibold mb-1">Tidak Ada Data User</h3>
                                <p class="text-slate-400 text-sm">Belum ada user yang ditambahkan</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>

            </table>
        </div>
    </div>

    @if ($users->hasPages())
        <div class="mt-6 flex justify-center">
            {{ $users->links('pagination::tailwind') }}
        </div>

        <p class="text-center text-slate-500 text-sm mt-2">
            Menampilkan {{ $users->firstItem() }} - {{ $users->lastItem() }} dari {{ $users->total() }} user
        </p>
    @endif

</div>

@endsection
