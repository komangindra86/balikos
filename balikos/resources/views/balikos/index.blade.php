@extends('layouts.dashboard', [
    'title' => 'Dashboard BALIKOS',
    'heading' => 'Dashboard BALIKOS',
    'subheading' => 'Statistik platform kos untuk monitoring Bali Santih'
])

@php
    $cards = [
        ['Total Pemilik', number_format($stats['total_pemilik']), 'Pemilik kos terdaftar'],
        ['Total Kos', number_format($stats['total_kos']), 'Kos aktif dan nonaktif'],
        ['Total Kamar', number_format($stats['total_kamar']), 'Seluruh kamar terdata'],
        ['Kamar Terisi', number_format($stats['total_kamar_terisi']), 'Kamar dengan penghuni aktif'],
        ['Kamar Kosong', number_format($stats['total_kamar_kosong']), 'Siap ditempati'],
        ['Maintenance', number_format($stats['total_kamar_maintenance']), 'Perlu perbaikan'],
        ['Penghuni Aktif', number_format($stats['total_penghuni_aktif']), 'Penghuni berjalan'],
        ['Okupansi', $stats['okupansi'].'%', 'Rasio kamar terisi'],
        ['Tagihan Bulan Ini', 'Rp '.number_format($stats['total_tagihan_bulan_ini'], 0, ',', '.'), 'Nominal periode berjalan'],
        ['Tagihan Lunas', 'Rp '.number_format($stats['total_tagihan_lunas'], 0, ',', '.'), 'Akumulasi tagihan lunas'],
        ['Tunggakan', 'Rp '.number_format($stats['total_tunggakan'], 0, ',', '.'), 'Belum lunas dan terlambat'],
        ['Harga Rata-rata', 'Rp '.number_format($stats['harga_rata_rata'], 0, ',', '.'), 'Rata-rata harga kamar'],
    ];
@endphp

@section('content')
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach($cards as [$label, $value, $hint])
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <div class="text-sm font-medium text-slate-500">{{ $label }}</div>
                <div class="mt-2 text-2xl font-semibold">{{ $value }}</div>
                <div class="mt-1 text-xs text-slate-400">{{ $hint }}</div>
            </div>
        @endforeach
    </div>

    <div class="mt-6 rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-2 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <h2 class="font-semibold">Kos Terbaru</h2>
            <a href="{{ route('balikos.kos') }}" class="text-sm font-medium text-teal-700 hover:text-teal-800">Lihat semua</a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[920px] text-left text-sm">
                <thead class="bg-slate-50 text-slate-500">
                    <tr>
                        <th class="px-5 py-3 font-medium">Nama Kos</th>
                        <th class="px-5 py-3 font-medium">Pemilik</th>
                        <th class="px-5 py-3 font-medium">Wilayah</th>
                        <th class="px-5 py-3 font-medium">Kamar</th>
                        <th class="px-5 py-3 font-medium">Terisi</th>
                        <th class="px-5 py-3 font-medium">Harga Rata-rata</th>
                        <th class="px-5 py-3 font-medium">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($recentKos as $row)
                        <tr>
                            <td class="px-5 py-3 font-medium">{{ $row->nama_kos }}</td>
                            <td class="px-5 py-3">{{ $row->owner_name }}</td>
                            <td class="px-5 py-3">{{ $row->kecamatan }} / {{ $row->desa ?? '-' }}</td>
                            <td class="px-5 py-3">{{ $row->jumlah_kamar }}</td>
                            <td class="px-5 py-3">{{ $row->kamar_terisi ?? 0 }}</td>
                            <td class="px-5 py-3">Rp {{ number_format($row->harga_rata_rata ?? 0, 0, ',', '.') }}</td>
                            <td class="px-5 py-3"><span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-medium">{{ $row->status }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-5 py-8 text-center text-slate-500">Belum ada data kos.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
