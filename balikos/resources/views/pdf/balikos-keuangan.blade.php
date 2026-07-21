<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Laporan Keuangan BALIKOS</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #172033; font-size: 12px; line-height: 1.45; }
        h1 { margin: 0 0 4px; color: #063a8f; font-size: 24px; }
        h2 { margin: 22px 0 8px; color: #063a8f; font-size: 15px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #d8e4f2; padding: 8px; vertical-align: top; }
        th { background: #eef6ff; text-align: left; }
        .muted { color: #5b6b80; }
        .summary { width: 100%; margin-top: 16px; }
        .summary td { width: 33.33%; border: 1px solid #d8e4f2; }
        .label { color: #5b6b80; font-size: 10px; text-transform: uppercase; }
        .value { display: block; margin-top: 4px; font-size: 15px; font-weight: bold; }
        .profit { color: #087f5b; }
        .loss { color: #c92a2a; }
        .right { text-align: right; }
        .center { text-align: center; }
        .footer { margin-top: 24px; color: #5b6b80; font-size: 10px; }
    </style>
</head>
<body>
@php
    $monthNames = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    $money = fn ($value) => 'Rp '.number_format((int) $value, 0, ',', '.');
    $period = ($monthNames[(int) $summary['bulan']] ?? $summary['bulan']).' '.$summary['tahun'];
@endphp

<h1>Laporan Keuangan BALIKOS</h1>
<div class="muted">{{ $kos?->nama_kos ?? 'Semua Kos' }} - Periode {{ $period }}</div>
<div class="muted">Dicetak {{ now()->format('d/m/Y H:i') }}</div>

<table class="summary">
    <tr>
        <td><span class="label">Pendapatan sewa</span><span class="value">{{ $money($summary['pendapatan_sewa']) }}</span></td>
        <td><span class="label">Pemasukan lain</span><span class="value">{{ $money($summary['pemasukan_lain']) }}</span></td>
        <td><span class="label">Total pemasukan</span><span class="value">{{ $money($summary['total_pemasukan']) }}</span></td>
    </tr>
    <tr>
        <td><span class="label">Pengeluaran</span><span class="value loss">{{ $money($summary['pengeluaran']) }}</span></td>
        <td><span class="label">Kerugian tunggakan</span><span class="value loss">{{ $money($summary['kerugian_tunggakan']) }}</span></td>
        <td><span class="label">Laba/Rugi</span><span class="value {{ $summary['laba_rugi'] >= 0 ? 'profit' : 'loss' }}">{{ $money($summary['laba_rugi']) }}</span></td>
    </tr>
</table>

<h2>Rincian Pendapatan Sewa</h2>
<table>
    <thead>
    <tr>
        <th>Kamar</th>
        <th>Penghuni</th>
        <th>Tanggal Bayar</th>
        <th>Metode</th>
        <th class="right">Nominal</th>
    </tr>
    </thead>
    <tbody>
    @forelse($rentBills as $bill)
        <tr>
            <td>{{ $bill->nomor_kamar ? 'Kamar '.$bill->nomor_kamar : '-' }}</td>
            <td>{{ $bill->nama_lengkap ?? '-' }}</td>
            <td>{{ $bill->tanggal_bayar ?? '-' }}</td>
            <td>{{ $bill->metode_pembayaran ?? '-' }}</td>
            <td class="right">{{ $money($bill->nominal) }}</td>
        </tr>
    @empty
        <tr><td colspan="5" class="center muted">Belum ada pembayaran sewa pada periode ini.</td></tr>
    @endforelse
    </tbody>
</table>

<h2>Kerugian Tunggakan Penghuni Keluar</h2>
<table>
    <thead>
    <tr>
        <th>Kamar</th>
        <th>Penghuni</th>
        <th>Tanggal Keluar</th>
        <th class="right">Kerugian</th>
    </tr>
    </thead>
    <tbody>
    @forelse($lossBills as $bill)
        <tr>
            <td>{{ $bill->nomor_kamar ? 'Kamar '.$bill->nomor_kamar : '-' }}</td>
            <td>{{ $bill->nama_lengkap ?? '-' }}</td>
            <td>{{ $bill->tanggal_kerugian ?? '-' }}</td>
            <td class="right">{{ $money($bill->nominal) }}</td>
        </tr>
    @empty
        <tr><td colspan="4" class="center muted">Tidak ada kerugian tunggakan pada periode ini.</td></tr>
    @endforelse
    </tbody>
</table>

<h2>Rincian Transaksi Manual</h2>
<table>
    <thead>
    <tr>
        <th>Tanggal</th>
        <th>Jenis</th>
        <th>Keterangan</th>
        <th class="right">Nominal</th>
    </tr>
    </thead>
    <tbody>
    @forelse($transactions as $item)
        <tr>
            <td>{{ $item->tanggal }}</td>
            <td>{{ ucfirst($item->jenis) }}</td>
            <td>{{ $item->keterangan ?: '-' }}</td>
            <td class="right">{{ $money($item->nominal) }}</td>
        </tr>
    @empty
        <tr><td colspan="4" class="center muted">Belum ada transaksi manual pada periode ini.</td></tr>
    @endforelse
    </tbody>
</table>

<div class="footer">
    Laporan ini dibuat otomatis oleh BALIKOS berdasarkan pembayaran sewa yang diterima, transaksi keuangan, dan tunggakan penghuni yang keluar.
</div>
</body>
</html>
