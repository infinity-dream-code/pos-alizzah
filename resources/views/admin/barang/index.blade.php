@extends('admin.template')
@section('content')

<div class="content-area">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:30px;flex-wrap:wrap;gap:16px;">
        <h2 style="font-size:32px;font-weight:700;color:#1e293b;margin:0;">Daftar Barang</h2>

        <a href="{{ url('admin/barang/create') }}" style="display:inline-flex;align-items:center;gap:10px;padding:14px 24px;background:linear-gradient(135deg,#4f46e5 0%,#7c3aed 100%);color:white;text-decoration:none;border-radius:12px;font-weight:600;font-size:15px;box-shadow:0 4px 16px rgba(79,70,229,0.3);transition:all 0.3s;">
            <svg style="width:20px;height:20px;fill:white;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
            Tambah Barang
        </a>
    </div>

    <div style="background:white;border-radius:16px;box-shadow:0 4px 20px rgba(0,0,0,0.08);padding:24px;margin-bottom:20px;border:1px solid #e2e8f0;">
        <form method="GET" action="{{ url('admin/barang') }}" style="display:flex;gap:12px;flex-wrap:wrap;align-items:end;">
            <div style="flex:1;min-width:250px;">
                <label style="display:block;color:#475569;font-size:14px;font-weight:600;margin-bottom:8px;">Cari Barang</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari kode atau nama barang..." style="width:100%;padding:12px 16px;border:2px solid #e2e8f0;border-radius:10px;font-size:14px;transition:all 0.3s;outline:none;" onfocus="this.style.borderColor='#4f46e5'" onblur="this.style.borderColor='#e2e8f0'">
            </div>


            <div style="display:flex;gap:8px;">
                <button type="submit" style="padding:12px 24px;background:linear-gradient(135deg,#4f46e5 0%,#7c3aed 100%);color:white;border:none;border-radius:10px;font-size:14px;font-weight:600;cursor:pointer;box-shadow:0 2px 8px rgba(79,70,229,0.3);transition:all 0.3s;display:inline-flex;align-items:center;gap:8px;">
                    <svg style="width:16px;height:16px;fill:white;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
                    Cari
                </button>

                @if(request('search') || request('category'))
                <a href="{{ url('admin/barang') }}" style="padding:12px 24px;background:#f1f5f9;color:#475569;border:none;border-radius:10px;font-size:14px;font-weight:600;text-decoration:none;display:inline-flex;align-items:center;gap:8px;transition:all 0.3s;">
                    <svg style="width:16px;height:16px;fill:#475569;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
                    Reset
                </a>
                @endif
            </div>
        </form>
    </div>

    <div style="background:white;border-radius:16px;box-shadow:0 4px 20px rgba(0,0,0,0.08);overflow:hidden;border:1px solid #e2e8f0;">
        <div style="overflow-x:auto;">
            <table style="width:100%;min-width:1000px;border-collapse:collapse;">
                <thead>
                    <tr style="background:linear-gradient(135deg,#f8fafc 0%,#e0e7ff 100%);">
                        <th style="padding:16px;font-weight:700;text-align:left;color:#1e293b;font-size:14px;border-bottom:2px solid #cbd5e1;">PLU</th>
                        <th style="padding:16px;font-weight:700;text-align:left;color:#1e293b;font-size:14px;border-bottom:2px solid #cbd5e1;">Nama</th>
                        <th style="padding:16px;font-weight:700;text-align:right;color:#1e293b;font-size:14px;border-bottom:2px solid #cbd5e1;">Harga Beli</th>
                        <th style="padding:16px;font-weight:700;text-align:right;color:#1e293b;font-size:14px;border-bottom:2px solid #cbd5e1;">Harga Jual</th>
                        <th style="padding:16px;font-weight:700;text-align:center;color:#1e293b;font-size:14px;border-bottom:2px solid #cbd5e1;">Stok</th>
                        <th style="padding:16px;font-weight:700;text-align:center;color:#1e293b;font-size:14px;border-bottom:2px solid #cbd5e1;">Status</th>
                        <th style="padding:16px;font-weight:700;text-align:center;color:#1e293b;font-size:14px;border-bottom:2px solid #cbd5e1;">Aksi</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($barangs as $b)
                    <tr style="border-bottom:1px solid #f1f5f9;transition:all 0.2s;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='white'">
                        <td style="padding:16px;color:#475569;font-weight:600;font-size:14px;">{{ $b->kode_barang }}</td>
                        <td style="padding:16px;color:#1e293b;font-weight:700;font-size:14px;">{{ $b->nama_barang }}</td>
                        
                        <td style="padding:16px;color:#64748b;font-size:14px;text-align:right;font-weight:600;">
                            Rp {{ number_format($b->harga_beli, 0, ',', '.') }}
                        </td>
                        <td style="padding:16px;color:#1e293b;font-size:14px;text-align:right;font-weight:700;">
                            Rp {{ number_format($b->harga_jual, 0, ',', '.') }}
                        </td>
                        <td style="padding:16px;text-align:center;">
                            <span style="padding:8px 16px;border-radius:8px;font-weight:700;font-size:14px;display:inline-block;color:{{ $b->stok > 10 ? '#059669' : ($b->stok > 0 ? '#d97706' : '#dc2626') }};background:{{ $b->stok > 10 ? '#d1fae5' : ($b->stok > 0 ? '#fef3c7' : '#fee2e2') }};">
                                {{ $b->stok }}
                            </span>
                        </td>
                        <td style="padding:16px;text-align:center;">
                            <span style="padding:8px 16px;border-radius:8px;color:white;font-weight:600;font-size:13px;display:inline-block;box-shadow:0 2px 8px {{ $b->status == 'aktif' ? 'rgba(16,185,129,0.3)' : 'rgba(239,68,68,0.3)' }};background:{{ $b->status == 'aktif' ? 'linear-gradient(135deg,#10b981,#059669)' : 'linear-gradient(135deg,#ef4444,#dc2626)' }};">
                                {{ ucfirst($b->status) }}
                            </span>
                        </td>
                        
                        <td style="padding:16px;">
                            <div style="display:flex;gap:8px;justify-content:center;flex-wrap:wrap;">
                                <a href="{{ route('barang.edit', $b->id) }}" style="padding:8px 16px;background:linear-gradient(135deg,#f59e0b,#d97706);color:white;border-radius:8px;text-decoration:none;font-size:13px;font-weight:600;box-shadow:0 2px 8px rgba(245,158,11,0.3);transition:all 0.3s;display:inline-flex;align-items:center;gap:6px;">
                                    <svg style="width:14px;height:14px;fill:white;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
                                    Edit
                                </a>


                                <form action="{{ url('admin/barang/destroy', $b->id) }}" method="POST" style="display:inline;" onsubmit="return confirm('Yakin ingin menghapus barang ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" style="padding:8px 16px;background:linear-gradient(135deg,#ef4444,#dc2626);color:white;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;box-shadow:0 2px 8px rgba(239,68,68,0.3);transition:all 0.3s;display:inline-flex;align-items:center;gap:6px;">
                                        <svg style="width:14px;height:14px;fill:white;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>
                                        Hapus
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" style="padding:60px 20px;text-align:center;">
                            <svg style="width:80px;height:80px;fill:#cbd5e1;margin:0 auto 20px;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zM8 20H4v-4h4v4zm0-6H4v-4h4v4zm0-6H4V4h4v4zm6 12h-4v-4h4v4zm0-6h-4v-4h4v4zm0-6h-4V4h4v4zm6 12h-4v-4h4v4zm0-6h-4v-4h4v4zm0-6h-4V4h4v4z"/></svg>
                            <h3 style="color:#64748b;font-size:18px;margin:0 0 8px;font-weight:600;">Tidak Ada Data Barang</h3>
                            <p style="color:#94a3b8;font-size:14px;margin:0;">Data barang tidak ditemukan</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($barangs->hasPages())
    <div style="margin-top:24px;display:flex;justify-content:center;">
        <div style="background:white;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,0.08);padding:12px 20px;display:inline-flex;gap:8px;align-items:center;border:1px solid #e2e8f0;">
            @if ($barangs->onFirstPage())
                <span style="padding:8px 12px;color:#cbd5e1;font-weight:600;font-size:14px;">‹</span>
            @else
                <a href="{{ $barangs->previousPageUrl() }}&search={{ request('search') }} }}" style="padding:8px 12px;color:#4f46e5;font-weight:600;text-decoration:none;border-radius:8px;transition:all 0.3s;font-size:14px;" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='transparent'">‹</a>
            @endif

            @foreach ($barangs->getUrlRange(1, $barangs->lastPage()) as $page => $url)
                @if ($page == $barangs->currentPage())
                    <span style="padding:8px 14px;background:linear-gradient(135deg,#4f46e5,#7c3aed);color:white;border-radius:8px;font-weight:700;font-size:14px;box-shadow:0 2px 8px rgba(79,70,229,0.3);">{{ $page }}</span>
                @else
                    <a href="{{ $url }}&search={{ request('search') }} }}" style="padding:8px 14px;color:#64748b;font-weight:600;text-decoration:none;border-radius:8px;transition:all 0.3s;font-size:14px;" onmouseover="this.style.background='#f1f5f9';this.style.color='#4f46e5'" onmouseout="this.style.background='transparent';this.style.color='#64748b'">{{ $page }}</a>
                @endif
            @endforeach

            @if ($barangs->hasMorePages())
                <a href="{{ $barangs->nextPageUrl() }}&search={{ request('search') }} }}" style="padding:8px 12px;color:#4f46e5;font-weight:600;text-decoration:none;border-radius:8px;transition:all 0.3s;font-size:14px;" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='transparent'">›</a>
            @else
                <span style="padding:8px 12px;color:#cbd5e1;font-weight:600;font-size:14px;">›</span>
            @endif
        </div>
    </div>

    <div style="text-align:center;margin-top:16px;color:#64748b;font-size:14px;">
        Menampilkan {{ $barangs->firstItem() ?? 0 }} - {{ $barangs->lastItem() ?? 0 }} dari {{ $barangs->total() }} barang
    </div>
    @endif
</div>

@endsection