@extends('layouts.dashboard', [
    'title' => 'Detail Pemilik Kos',
    'heading' => $owner->name,
    'subheading' => 'Detail pemilik dan daftar kos miliknya'
])

@section('content')
    <div class="grid gap-4 xl:grid-cols-[1fr_2fr]">
        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <div class="text-sm text-slate-500">Email</div>
            <div class="font-medium">{{ $owner->email }}</div>
            <div class="mt-4 text-sm text-slate-500">Telepon</div>
            <div class="font-medium">{{ $owner->phone ?? '-' }}</div>
            <div class="mt-4 text-sm text-slate-500">Status</div>
            <div><span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-medium">{{ $owner->status }}</span></div>

            <div class="mt-5">
                <a href="{{ route('balikos.pemilik') }}" class="rounded-md border border-slate-200 px-3 py-2 text-sm font-semibold hover:bg-slate-50">Kembali</a>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach([
                'Total Kos' => $summary['total_kos'],
                'Total Kamar' => $summary['total_kamar'],
                'Penghuni Aktif' => $summary['penghuni_aktif'],
                'Tunggakan' => 'Rp '.number_format($summary['tunggakan'], 0, ',', '.'),
            ] as $label => $value)
                <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="text-sm text-slate-500">{{ $label }}</div>
                    <div class="mt-2 text-2xl font-semibold">{{ $value }}</div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="mt-6 rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-4 font-semibold">Daftar Kos</div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[760px] text-left text-sm">
                <thead class="bg-slate-50 text-slate-500">
                    <tr>
                        <th class="px-5 py-3 font-medium">Nama Kos</th>
                        <th class="px-5 py-3 font-medium">Wilayah</th>
                        <th class="px-5 py-3 font-medium">Kamar</th>
                        <th class="px-5 py-3 font-medium">Terisi</th>
                        <th class="px-5 py-3 font-medium">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($kos as $row)
                        <tr>
                            <td class="px-5 py-3 font-medium">{{ $row->nama_kos }}</td>
                            <td class="px-5 py-3">{{ $row->kecamatan }} / {{ $row->desa ?? '-' }} / {{ $row->banjar ?? '-' }}</td>
                            <td class="px-5 py-3">{{ $row->jumlah_kamar }}</td>
                            <td class="px-5 py-3">{{ $row->kamar_terisi ?? 0 }}</td>
                            <td class="px-5 py-3">{{ $row->status }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-8 text-center text-slate-500">Pemilik ini belum memiliki kos.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
