<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Transaksi</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 11px; color: #333; margin: 20px; }
        h2 { text-align: center; margin-bottom: 8px; font-size: 18px; text-transform: uppercase; letter-spacing: 1px; }
        .subtitle { text-align: center; margin-bottom: 5px; font-size: 10px; color: #666; }
        .filter-info { text-align: center; margin-bottom: 20px; font-size: 10px; color: #444; padding: 8px; background: #f5f5f5; border-radius: 4px; }
        .filter-info strong { font-weight: 600; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table, th, td { border: 1px solid #333; }
        th { background: #e8e8e8; padding: 10px 8px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
        td { padding: 8px; font-size: 10px; }
        .right { text-align: right; }
        .center { text-align: center; }
        .summary { margin-top: 25px; width: 40%; float: right; }
        .summary td { padding: 8px 10px; font-size: 11px; }
        .summary td:first-child { font-weight: 600; width: 60%; }
        .summary td:last-child { text-align: right; width: 40%; }
    </style>
</head>

<body>

    <h2>Laporan Transaksi</h2>

    <div class="subtitle">
        Dicetak pada: {{ \Carbon\Carbon::now('Asia/Jakarta')->format('d M Y H:i') }}
    </div>

    @if(request('tanggal_dari') || request('tanggal_sampai') || request('metode'))
        <div class="filter-info">
            <strong>Filter:</strong>
            @if(request('tanggal_dari') && request('tanggal_sampai'))
                Periode: {{ \Carbon\Carbon::parse(request('tanggal_dari'))->format('d M Y') }} - {{ \Carbon\Carbon::parse(request('tanggal_sampai'))->format('d M Y') }}
            @elseif(request('tanggal_dari'))
                Dari: {{ \Carbon\Carbon::parse(request('tanggal_dari'))->format('d M Y') }}
            @elseif(request('tanggal_sampai'))
                Sampai: {{ \Carbon\Carbon::parse(request('tanggal_sampai'))->format('d M Y') }}
            @endif

            @if(request('metode'))
                @if(request('tanggal_dari') || request('tanggal_sampai')) |
                @endif
                Metode: {{ ucfirst(request('metode')) }}
            @endif
        </div>
    @endif

    @php
        $no = 1;
        $totalPendapatan = 0;
        $totalKeuntungan = 0;
    @endphp

    <table>
        <thead>
            <tr>
                <th class="center" style="width: 5%;">No</th>
                <th style="width: 18%;">Kode Transaksi</th>
                <th style="width: 17%;">Tanggal</th>
                <th style="width: 15%;">Kasir</th>
                <th class="right" style="width: 15%;">Total</th>
                <th class="right" style="width: 15%;">Keuntungan</th>
                <th class="center" style="width: 15%;">Metode</th>
            </tr>
        </thead>

        <tbody>
            @foreach ($transaksis as $t)
                @php
                    $totalPendapatan += $t->grand_total;
                    $profitTransaksi = $t->profit ?? 0;
                    $totalKeuntungan += $profitTransaksi;
                @endphp

                <tr>
                    <td class="center">{{ $no++ }}</td>
                    <td>{{ $t->kode_transaksi }}</td>
                    <td>{{ \Carbon\Carbon::parse($t->tanggal)->format('d M Y') }}</td>
                    <td>{{ $t->user->nama ?? '-' }}</td>
                    <td class="right">Rp {{ number_format($t->grand_total, 0, ',', '.') }}</td>
                    <td class="right">Rp {{ number_format($profitTransaksi, 0, ',', '.') }}</td>
                    <td class="center">{{ ucfirst($t->metode) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="summary">
        <tr>
            <td>Total Transaksi</td>
            <td>{{ count($transaksis) }}</td>
        </tr>
        <tr>
            <td>Total Pendapatan</td>
            <td>Rp {{ number_format($totalPendapatan, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td>Total Keuntungan</td>
            <td>Rp {{ number_format($totalKeuntungan, 0, ',', '.') }}</td>
        </tr>
    </table>

</body>
</html>
