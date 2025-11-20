@extends('admin.template')

@section('content')

<div class="content-area">

    <h2 class="text-3xl font-bold text-slate-800 mb-6">Edit User</h2>

    <div class="bg-white shadow-xl border border-slate-200 rounded-xl p-8">

        <form action="{{ route('user.update', $user->id) }}" method="POST" class="space-y-6">
            @csrf
            @method('PUT')

            <div>
                <label class="font-semibold text-slate-700 mb-1 block">Nama</label>
                <input type="text" name="nama" value="{{ $user->nama }}" required
                       class="w-full px-4 py-3 border border-slate-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
            </div>

            <div>
                <label class="font-semibold text-slate-700 mb-1 block">Username</label>
                <input type="text" name="username" value="{{ $user->username }}" required
                       class="w-full px-4 py-3 border border-slate-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
            </div>

            <div>
                <label class="font-semibold text-slate-700 mb-1 block">
                    Password (kosongkan jika tidak diganti)
                </label>
                <input type="password" name="password"
                       class="w-full px-4 py-3 border border-slate-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
            </div>

            <div>
                <label class="font-semibold text-slate-700 mb-1 block">Role</label>
                <select name="role"
                        class="w-full px-4 py-3 border border-slate-300 rounded-lg bg-white focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="kasir" {{ $user->role == 'kasir' ? 'selected' : '' }}>Kasir</option>
                    <option value="admin" {{ $user->role == 'admin' ? 'selected' : '' }}>Admin</option>
                </select>
            </div>

            <div class="flex gap-4 pt-4">
                <button type="submit"
                        class="px-7 py-3 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-semibold shadow-lg">
                    Update
                </button>

                <a href="{{ route('user.index') }}"
                   class="px-7 py-3 bg-slate-300 text-slate-800 hover:bg-slate-400 rounded-lg font-semibold">
                    Batal
                </a>
            </div>

        </form>

    </div>

</div>

@endsection
