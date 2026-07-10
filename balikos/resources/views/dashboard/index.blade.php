@extends('layouts.dashboard', [
    'title' => 'Dashboard Bali Santih',
    'heading' => 'Dashboard',
    'subheading' => 'Ringkasan awal platform Bali Santih dan module BALIKOS'
])

@section('content')
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach([
            'Total Kos' => $stats['total_kos'],
            'Total Kamar' => $stats['total_kamar'],
            'Kamar Terisi' => $stats['kamar_terisi'],
            'Penghuni Aktif' => $stats['penghuni_aktif'],
        ] as $label => $value)
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <div class="text-sm text-slate-500">{{ $label }}</div>
                <div class="mt-2 text-3xl font-semibold">{{ number_format($value) }}</div>
            </div>
        @endforeach
    </div>

    <div class="mt-6 rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-4">
            <h2 class="font-semibold">Kos Terbaru</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[720px] text-left text-sm">
                <thead class="bg-slate-50 text-slate-500">
                    <tr>
                        <th class="px-5 py-3 font-medium">Nama Kos</th>
                        <th class="px-5 py-3 font-medium">Kecamatan</th>
                        <th class="px-5 py-3 font-medium">Desa</th>
                        <th class="px-5 py-3 font-medium">Kamar</th>
                        <th class="px-5 py-3 font-medium">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($latestKos as $row)
                        <tr>
                            <td class="px-5 py-3 font-medium">{{ $row->nama_kos }}</td>
                            <td class="px-5 py-3">{{ $row->kecamatan }}</td>
                            <td class="px-5 py-3">{{ $row->desa ?? '-' }}</td>
                            <td class="px-5 py-3">{{ $row->jumlah_kamar }}</td>
                            <td class="px-5 py-3"><span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-medium">{{ $row->status }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-8 text-center text-slate-500">Belum ada data kos.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
