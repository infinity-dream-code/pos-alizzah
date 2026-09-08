<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Transaksi</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 11px; color:#333; margin:20px; }
        h2 { text-align:center; margin-bottom:4px; font-size:18px; text-transform:uppercase; }
        .subtitle { text-align:center; font-size:10px; color:#666; margin-bottom:10px; }
        .filter-info { text-align:center; background:#f1f1f1; border-radius:4px; padding:10px; font-size:10px; margin-bottom:20px; }
        table { width: 100%; border-collapse: collapse; margin-top:10px; }
        table, th, td { border:1px solid #333; }
        th { background:#e8e8e8; padding:8px; text-transform:uppercase; font-size:10px; }
        td { padding:7px; font-size:10px; }
        .right { text-align:right; }
        .center { text-align:center; }
        .summary { width:40%; float:right; margin-top:20px; }
        .summary td { padding:8px 10px; font-size:11px; }
        .summary td:first-child { font-weight:600; }
        .top3 { margin-top:40px; width:50%; }
        .top3 th { background:#dcdcdc; }
    </style>
</head>

<body>

<h2>Laporan Transaksi</h2>
<div class="subtitle">Dicetak pada: {{ \Carbon\Carbon::now('Asia/Jakarta')->format('d M Y H:i') }}</div>

@if(request()->filled('tanggal_dari') || request()->filled('tanggal_sampai') || request()->filled('metode') || request()->filled('diskon_min') || request()->filled('diskon_max'))
<div class="filter-info">
    <strong>Filter: </strong>

    @if(request('tanggal_dari') && request('tanggal_sampai'))
        Periode: {{ \Carbon\Carbon::parse(request('tanggal_dari'))->format('d M Y') }} - {{ \Carbon\Carbon::parse(request('tanggal_sampai'))->format('d M Y') }}
    @elseif(request('tanggal_dari'))
        Dari: {{ \Carbon\Carbon::parse(request('tanggal_dari'))->format('d M Y') }}
    @elseif(request('tanggal_sampai'))
        Sampai: {{ \Carbon\Carbon::parse(request('tanggal_sampai'))->format('d M Y') }}
    @endif

    @if(request('metode'))
        | Metode: {{ ucfirst(request('metode')) }}
    @endif

    @if(request('diskon_min') || request('diskon_max'))
        | Diskon:
        @if(request('diskon_min') && request('diskon_max'))
            {{ request('diskon_min') }}% - {{ request('diskon_max') }}%
        @elseif(request('diskon_min'))
            ≥ {{ request('diskon_min') }}%
        @else
            ≤ {{ request('diskon_max') }}%
        @endif
    @endif
</div>
@endif

@php
    $no = 1;
    $totalPendapatan = 0;
    $totalKeuntungan = 0;
    $totalDiskon = 0;
@endphp

<table>
<thead>
<tr>
    <th class="center" style="width:5%">No</th>
    <th style="width:15%">Kode Transaksi</th>
    <th style="width:15%">Tanggal</th>
    <th style="width:13%">Kasir</th>
    <th class="right" style="width:15%">Total</th>
    <th class="right" style="width:15%">Keuntungan</th>
    <th class="right" style="width:12%">Diskon</th>
    <th class="center" style="width:10%">Metode</th>
</tr>
</thead>

<tbody>
@foreach($transaksis as $t)
@php
    $diskonTransaksi = $t->detail->sum('diskon_nominal');
    $totalDiskon += $diskonTransaksi;
    $totalPendapatan += $t->grand_total;
    $profit = $t->profit ?? 0;
    $totalKeuntungan += $profit;
@endphp
<tr>
    <td class="center">{{ $no++ }}</td>
    <td>{{ $t->kode_transaksi }}</td>
    <td>{{ \Carbon\Carbon::parse($t->tanggal)->format('d M Y') }}</td>
    <td>{{ $t->user->nama ?? '-' }}</td>
    <td class="right">Rp {{ number_format($t->grand_total,0,',','.') }}</td>
    <td class="right">Rp {{ number_format($profit,0,',','.') }}</td>
    <td class="right">Rp {{ number_format($diskonTransaksi,0,',','.') }}</td>
    <td class="center">{{ ucfirst($t->metode) }}</td>
</tr>
@endforeach
</tbody>
</table>

<table class="summary">
<tr><td>Total Transaksi</td><td>{{ count($transaksis) }}</td></tr>
<tr><td>Total Pendapatan</td><td>Rp {{ number_format($totalPendapatan,0,',','.') }}</td></tr>
<tr><td>Total Keuntungan</td><td>Rp {{ number_format($totalKeuntungan,0,',','.') }}</td></tr>
<tr><td>Total Diskon</td><td>Rp {{ number_format($totalDiskon,0,',','.') }}</td></tr>
</table>

</tbody>
</table>

</body>
</html>
