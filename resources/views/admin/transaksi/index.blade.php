@extends('admin.template')
@section('content')

<div class="content-area">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:30px;flex-wrap:wrap;gap:16px;">
        <h2 style="font-size:32px;font-weight:700;color:#1e293b;margin:0;">Riwayat Transaksi</h2>
    </div>

    @if(session('success'))
    <div style="background:#d1fae5;border:2px solid #10b981;color:#065f46;padding:16px 20px;border-radius:12px;margin-bottom:24px;display:flex;align-items:center;gap:12px;">
        <svg style="width:24px;height:24px;fill:#10b981;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
        <span style="font-weight:600;">{{ session('success') }}</span>
    </div>
    @endif

    <div style="background:white;border-radius:16px;box-shadow:0 4px 20px rgba(0,0,0,0.08);padding:24px;margin-bottom:20px;border:1px solid #e2e8f0;">
        <form method="GET" action="{{ route('transaksi.index') }}">
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:16px;">
                <div>
                    <label style="display:block;color:#475569;font-size:14px;font-weight:600;margin-bottom:8px;">Cari Transaksi</label>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Kode transaksi / Nama kasir" style="width:100%;padding:12px 16px;border:2px solid #e2e8f0;border-radius:10px;font-size:14px;transition:all 0.3s;outline:none;" onfocus="this.style.borderColor='#4f46e5'" onblur="this.style.borderColor='#e2e8f0'">
                </div>

                <div>
                    <label style="display:block;color:#475569;font-size:14px;font-weight:600;margin-bottom:8px;">Metode Pembayaran</label>
                    <select name="metode" style="width:100%;padding:12px 16px;border:2px solid #e2e8f0;border-radius:10px;font-size:14px;transition:all 0.3s;outline:none;background:white;" onfocus="this.style.borderColor='#4f46e5'" onblur="this.style.borderColor='#e2e8f0'">
                        <option value="">Semua Metode</option>
                        <option value="tunai" {{ request('metode') == 'tunai' ? 'selected' : '' }}>Tunai</option>
                        <option value="online" {{ request('metode') == 'online' ? 'selected' : '' }}>Online</option>
                    </select>
                </div>

                <div>
                    <label style="display:block;color:#475569;font-size:14px;font-weight:600;margin-bottom:8px;">Tanggal Dari</label>
                    <input type="date" name="tanggal_dari" value="{{ request('tanggal_dari') }}" style="width:100%;padding:12px 16px;border:2px solid #e2e8f0;border-radius:10px;font-size:14px;transition:all 0.3s;outline:none;" onfocus="this.style.borderColor='#4f46e5'" onblur="this.style.borderColor='#e2e8f0'">
                </div>

                <div>
                    <label style="display:block;color:#475569;font-size:14px;font-weight:600;margin-bottom:8px;">Tanggal Sampai</label>
                    <input type="date" name="tanggal_sampai" value="{{ request('tanggal_sampai') }}" style="width:100%;padding:12px 16px;border:2px solid #e2e8f0;border-radius:10px;font-size:14px;transition:all 0.3s;outline:none;" onfocus="this.style.borderColor='#4f46e5'" onblur="this.style.borderColor='#e2e8f0'">
                </div>
            </div>

            <div style="display:flex;gap:8px;justify-content:flex-end;">
                <button type="submit" style="padding:12px 24px;background:linear-gradient(135deg,#4f46e5 0%,#7c3aed 100%);color:white;border:none;border-radius:10px;font-size:14px;font-weight:600;cursor:pointer;box-shadow:0 2px 8px rgba(79,70,229,0.3);transition:all 0.3s;display:inline-flex;align-items:center;gap:8px;">
                    <svg style="width:16px;height:16px;fill:white;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
                    Filter
                </button>

                @if(request('search') || request('metode') || request('tanggal_dari') || request('tanggal_sampai'))
                <a href="{{ route('transaksi.index') }}" style="padding:12px 24px;background:#f1f5f9;color:#475569;border:none;border-radius:10px;font-size:14px;font-weight:600;text-decoration:none;display:inline-flex;align-items:center;gap:8px;transition:all 0.3s;">
                    <svg style="width:16px;height:16px;fill:#475569;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
                    Reset
                </a>
                @endif
            </div>
        </form>
    </div>

    <div style="background:white;border-radius:16px;box-shadow:0 4px 20px rgba(0,0,0,0.08);overflow:hidden;border:1px solid #e2e8f0;">
        <div style="overflow-x:auto;">
            <table style="width:100%;min-width:900px;border-collapse:collapse;">
                <thead>
                    <tr style="background:linear-gradient(135deg,#f8fafc 0%,#e0e7ff 100%);">
                        <th style="padding:16px;font-weight:700;text-align:center;color:#1e293b;font-size:14px;border-bottom:2px solid #cbd5e1;">No</th>
                        <th style="padding:16px;font-weight:700;text-align:left;color:#1e293b;font-size:14px;border-bottom:2px solid #cbd5e1;">Kode Transaksi</th>
                        <th style="padding:16px;font-weight:700;text-align:left;color:#1e293b;font-size:14px;border-bottom:2px solid #cbd5e1;">Tanggal</th>
                        <th style="padding:16px;font-weight:700;text-align:left;color:#1e293b;font-size:14px;border-bottom:2px solid #cbd5e1;">Kasir</th>
                        <th style="padding:16px;font-weight:700;text-align:right;color:#1e293b;font-size:14px;border-bottom:2px solid #cbd5e1;">Total</th>
                        <th style="padding:16px;font-weight:700;text-align:center;color:#1e293b;font-size:14px;border-bottom:2px solid #cbd5e1;">Metode</th>
                        <th style="padding:16px;font-weight:700;text-align:center;color:#1e293b;font-size:14px;border-bottom:2px solid #cbd5e1;">Aksi</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($transaksis as $index => $t)
                    <tr style="border-bottom:1px solid #f1f5f9;transition:all 0.2s;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='white'">
                        <td style="padding:16px;text-align:center;color:#64748b;font-weight:600;font-size:14px;">{{ $transaksis->firstItem() + $index }}</td>
                        <td style="padding:16px;color:#4f46e5;font-weight:700;font-size:14px;">{{ $t->kode_transaksi }}</td>
                        <td style="padding:16px;color:#64748b;font-size:14px;">{{ $t->tanggal->format('d M Y') }}</td>
                        <td style="padding:16px;color:#1e293b;font-weight:600;font-size:14px;">{{ $t->user->nama ?? '-' }}</td>
                        <td style="padding:16px;text-align:right;color:#1e293b;font-weight:700;font-size:14px;">Rp {{ number_format($t->grand_total ?? $t->total, 0, ',', '.') }}</td>
                        <td style="padding:16px;text-align:center;">
                            @if($t->metode == 'tunai')
                            <span style="padding:8px 16px;border-radius:8px;color:white;font-weight:600;font-size:13px;display:inline-block;background:linear-gradient(135deg,#10b981,#059669);box-shadow:0 2px 8px rgba(16,185,129,0.3);">
                                Tunai
                            </span>
                            @else
                            <span style="padding:8px 16px;border-radius:8px;color:white;font-weight:600;font-size:13px;display:inline-block;background:linear-gradient(135deg,#3b82f6,#2563eb);box-shadow:0 2px 8px rgba(59,130,246,0.3);">
                                Online
                            </span>
                            @endif
                        </td>
                        <td style="padding:16px;">
                            <div style="display:flex;gap:8px;justify-content:center;flex-wrap:wrap;">
                                <button onclick="showDetail({{ $t->id }})" style="padding:8px 16px;background:linear-gradient(135deg,#3b82f6,#2563eb);color:white;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;box-shadow:0 2px 8px rgba(59,130,246,0.3);transition:all 0.3s;display:inline-flex;align-items:center;gap:6px;">
                                    <svg style="width:14px;height:14px;fill:white;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg>
                                    Detail
                                </button>

                              
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" style="padding:60px 20px;text-align:center;">
                            <svg style="width:80px;height:80px;fill:#cbd5e1;margin:0 auto 20px;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zM8 20H4v-4h4v4zm0-6H4v-4h4v4zm0-6H4V4h4v4zm6 12h-4v-4h4v4zm0-6h-4v-4h4v4zm0-6h-4V4h4v4zm6 12h-4v-4h4v4zm0-6h-4v-4h4v4zm0-6h-4V4h4v4z"/></svg>
                            <h3 style="color:#64748b;font-size:18px;margin:0 0 8px;font-weight:600;">Tidak Ada Data Transaksi</h3>
                            <p style="color:#94a3b8;font-size:14px;margin:0;">Data transaksi tidak ditemukan</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
            <div style="padding: 20px;">
    {{ $transaksis->links() }}
</div>
        </div>
    </div>
</div>

<div id="detailModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;padding:20px;">
    <div style="background:white;border-radius:16px;max-width:1000px;width:100%;max-height:90vh;overflow:auto;box-shadow:0 20px 60px rgba(0,0,0,0.3);">
        <div style="position:sticky;top:0;background:white;padding:24px 30px;border-bottom:2px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;z-index:10;">
            <h3 style="color:#1e293b;font-size:24px;font-weight:700;margin:0;">Detail Transaksi</h3>
            <button onclick="closeDetail()" style="background:#f1f5f9;border:none;color:#64748b;width:36px;height:36px;border-radius:50%;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all 0.3s;" onmouseover="this.style.background='#e2e8f0'" onmouseout="this.style.background='#f1f5f9'">
                <svg style="width:20px;height:20px;fill:#64748b;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
            </button>
        </div>

        <div id="detailContent" style="padding:30px;">
        </div>
    </div>
</div>

<script>
const transaksiData = {!! $transaksis->map(function($t) {
    return [
        'id' => $t->id,
        'kode_transaksi' => $t->kode_transaksi,
        'tanggal' => $t->tanggal->format('d M Y'),
        'kasir' => $t->user->nama ?? '-',
        'total' => $t->total,
        'bayar' => $t->bayar,
        'kembalian' => $t->kembalian,
        'diskon' => $t->diskon_nominal ?? 0,
        'grand_total' => $t->grand_total ?? $t->total,
        'metode' => $t->metode,
        'items' => $t->detail->map(function($d) {
            return [
                'nama' => $d->barang->nama_barang ?? '-',
                'kode' => $d->barang->kode_barang ?? '-',
                'qty' => $d->qty,
                'harga' => $d->harga,
                'subtotal' => $d->subtotal
            ];
        })
    ];
})->toJson() !!};

function showDetail(id) {
    const transaksi = transaksiData.find(t => t.id === id);
    if (!transaksi) return;

    let itemsHtml = '';
    transaksi.items.forEach((item, index) => {
        const rowBg = index % 2 === 0 ? '#f8fafc' : 'white';
        itemsHtml += `
            <tr style="background:${rowBg};">
                <td style="padding:14px;text-align:center;color:#64748b;font-weight:600;">${index + 1}</td>
                <td style="padding:14px;color:#64748b;font-weight:600;">${item.kode}</td>
                <td style="padding:14px;color:#1e293b;font-weight:600;">${item.nama}</td>
                <td style="padding:14px;text-align:center;color:#1e293b;font-weight:700;">${item.qty}</td>
                <td style="padding:14px;text-align:right;color:#64748b;font-weight:600;">Rp ${item.harga.toLocaleString('id-ID')}</td>
                <td style="padding:14px;text-align:right;color:#1e293b;font-weight:700;">Rp ${item.subtotal.toLocaleString('id-ID')}</td>
            </tr>
        `;
    });

    const metodeBadge = transaksi.metode === 'tunai' 
        ? '<span style="padding:6px 16px;border-radius:6px;background:#10b981;color:white;font-weight:600;font-size:13px;display:inline-block;">Tunai</span>'
        : '<span style="padding:6px 16px;border-radius:6px;background:#3b82f6;color:white;font-weight:600;font-size:13px;display:inline-block;">Online</span>';

    document.getElementById('detailContent').innerHTML = `
        <div style="background:#f8fafc;padding:20px;border-radius:12px;margin-bottom:24px;border:1px solid #e2e8f0;">
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;">
                <div>
                    <p style="color:#64748b;font-size:12px;font-weight:600;margin:0 0 6px 0;">Kode Transaksi</p>
                    <p style="color:#1e293b;font-size:16px;font-weight:700;margin:0;">${transaksi.kode_transaksi}</p>
                </div>
                <div>
                    <p style="color:#64748b;font-size:12px;font-weight:600;margin:0 0 6px 0;">Tanggal Transaksi</p>
                    <p style="color:#1e293b;font-size:16px;font-weight:700;margin:0;">${transaksi.tanggal}</p>
                </div>
                <div>
                    <p style="color:#64748b;font-size:12px;font-weight:600;margin:0 0 6px 0;">Kasir</p>
                    <p style="color:#1e293b;font-size:16px;font-weight:700;margin:0;">${transaksi.kasir}</p>
                </div>
                <div>
                    <p style="color:#64748b;font-size:12px;font-weight:600;margin:0 0 8px 0;">Metode Pembayaran</p>
                    ${metodeBadge}
                </div>
            </div>
        </div>

        <div style="margin-bottom:24px;">
            <h4 style="color:#1e293b;font-size:18px;font-weight:700;margin:0 0 16px 0;">Daftar Barang</h4>
            <div style="overflow-x:auto;border:1px solid #e2e8f0;border-radius:12px;">
                <table style="width:100%;border-collapse:collapse;background:white;">
                    <thead>
                        <tr style="background:#f8fafc;border-bottom:2px solid #e2e8f0;">
                            <th style="padding:14px;color:#1e293b;font-weight:700;text-align:center;font-size:13px;">No</th>
                            <th style="padding:14px;color:#1e293b;font-weight:700;text-align:left;font-size:13px;">Kode</th>
                            <th style="padding:14px;color:#1e293b;font-weight:700;text-align:left;font-size:13px;">Nama Barang</th>
                            <th style="padding:14px;color:#1e293b;font-weight:700;text-align:center;font-size:13px;">Qty</th>
                            <th style="padding:14px;color:#1e293b;font-weight:700;text-align:right;font-size:13px;">Harga</th>
                            <th style="padding:14px;color:#1e293b;font-weight:700;text-align:right;font-size:13px;">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${itemsHtml}
                    </tbody>
                </table>
            </div>
        </div>

        <div style="background:#f8fafc;padding:24px;border-radius:12px;border:1px solid #e2e8f0;">
            <h4 style="color:#1e293b;font-size:18px;font-weight:700;margin:0 0 20px 0;">Ringkasan Pembayaran</h4>
            <div style="display:grid;gap:12px;">
                <div style="display:flex;justify-content:space-between;padding:12px 0;border-bottom:1px solid #e2e8f0;">
                    <span style="color:#64748b;font-weight:600;font-size:14px;">Total</span>
                    <span style="color:#1e293b;font-weight:700;font-size:14px;">Rp ${transaksi.total.toLocaleString('id-ID')}</span>
                </div>
                <div style="display:flex;justify-content:space-between;padding:12px 0;border-bottom:1px solid #e2e8f0;">
                    <span style="color:#64748b;font-weight:600;font-size:14px;">Diskon</span>
                    <span style="color:#ef4444;font-weight:700;font-size:14px;">- Rp ${transaksi.diskon.toLocaleString('id-ID')}</span>
                </div>
                <div style="display:flex;justify-content:space-between;padding:12px 0;border-bottom:2px solid #cbd5e1;">
                    <span style="color:#1e293b;font-weight:700;font-size:16px;">Grand Total</span>
                    <span style="color:#4f46e5;font-weight:700;font-size:18px;">Rp ${transaksi.grand_total.toLocaleString('id-ID')}</span>
                </div>
                <div style="display:flex;justify-content:space-between;padding:12px 0;border-bottom:1px solid #e2e8f0;">
                    <span style="color:#64748b;font-weight:600;font-size:14px;">Bayar</span>
                    <span style="color:#1e293b;font-weight:700;font-size:14px;">Rp ${transaksi.bayar.toLocaleString('id-ID')}</span>
                </div>
                <div style="display:flex;justify-content:space-between;padding:16px 20px;background:#10b981;border-radius:8px;margin-top:4px;">
                    <span style="color:white;font-weight:700;font-size:16px;">Kembalian</span>
                    <span style="color:white;font-weight:700;font-size:20px;">Rp ${transaksi.kembalian.toLocaleString('id-ID')}</span>
                </div>
            </div>
        </div>

        <div style="margin-top:24px;text-align:center;">
            <button onclick="closeDetail()" style="padding:12px 40px;background:#64748b;color:white;border:none;border-radius:10px;font-size:15px;font-weight:600;cursor:pointer;transition:all 0.3s;" onmouseover="this.style.background='#475569'" onmouseout="this.style.background='#64748b'">
                Tutup
            </button>
        </div>
    `;

    document.getElementById('detailModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeDetail() {
    document.getElementById('detailModal').style.display = 'none';
    document.body.style.overflow = 'auto';
}

document.getElementById('detailModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeDetail();
    }
});
</script>

@endsection