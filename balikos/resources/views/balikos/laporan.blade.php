@extends('layouts.dashboard', [
    'title' => 'Laporan Platform',
    'heading' => 'Laporan Platform',
    'subheading' => 'Pertumbuhan data dan wilayah prioritas BALIKOS'
])

@section('content')
    <div class="grid gap-6 xl:grid-cols-2">
        <div class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4 font-semibold">Pertumbuhan Jumlah Kos</div>
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-slate-500"><tr><th class="px-5 py-3">Bulan</th><th class="px-5 py-3">Total Kos Baru</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($growth as $row)
                        <tr><td class="px-5 py-3">{{ $row->bulan }}</td><td class="px-5 py-3">{{ $row->total_kos }}</td></tr>
                    @empty
                        <tr><td colspan="2" class="px-5 py-8 text-center text-slate-500">Belum ada data.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4 font-semibold">Pertumbuhan Jumlah Kamar</div>
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-slate-500"><tr><th class="px-5 py-3">Bulan</th><th class="px-5 py-3">Total Kamar Baru</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($roomGrowth as $row)
                        <tr><td class="px-5 py-3">{{ $row->bulan }}</td><td class="px-5 py-3">{{ $row->total_kamar }}</td></tr>
                    @empty
                        <tr><td colspan="2" class="px-5 py-8 text-center text-slate-500">Belum ada data.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4 font-semibold">Wilayah Harga Tertinggi</div>
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-slate-500"><tr><th class="px-5 py-3">Kecamatan</th><th class="px-5 py-3">Harga Rata-rata</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($highestPriceAreas as $row)
                        <tr><td class="px-5 py-3">{{ $row->kecamatan }}</td><td class="px-5 py-3">Rp {{ number_format($row->harga_rata_rata, 0, ',', '.') }}</td></tr>
                    @empty
                        <tr><td colspan="2" class="px-5 py-8 text-center text-slate-500">Belum ada data.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4 font-semibold">Wilayah Okupansi Tertinggi</div>
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-slate-500"><tr><th class="px-5 py-3">Kecamatan</th><th class="px-5 py-3">Okupansi</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($highestOccupancyAreas as $row)
                        <tr><td class="px-5 py-3">{{ $row->kecamatan }}</td><td class="px-5 py-3">{{ $row->okupansi }}%</td></tr>
                    @empty
                        <tr><td colspan="2" class="px-5 py-8 text-center text-slate-500">Belum ada data.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
