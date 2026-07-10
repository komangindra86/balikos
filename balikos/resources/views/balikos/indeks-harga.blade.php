@extends('layouts.dashboard', [
    'title' => 'Indeks Harga Kos',
    'heading' => 'Indeks Harga Kos',
    'subheading' => 'Agregasi harga, kamar, dan okupansi per kecamatan'
])

@section('content')
    <form method="get" class="mb-4 grid gap-3 rounded-lg border border-slate-200 bg-white p-4 shadow-sm xl:grid-cols-4">
        @foreach(['kecamatan' => 'Kecamatan', 'desa' => 'Desa', 'banjar' => 'Banjar'] as $field => $label)
            <label class="grid gap-1">
                <span class="text-xs font-medium text-slate-500">{{ $label }}</span>
                <select name="{{ $field }}" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Semua</option>
                    @foreach($filterOptions[$field] as $option)
                        <option value="{{ $option }}" @selected(($filters[$field] ?? '') === $option)>{{ $option }}</option>
                    @endforeach
                </select>
            </label>
        @endforeach
        <div class="grid gap-3 sm:grid-cols-2">
            <label class="grid gap-1">
                <span class="text-xs font-medium text-slate-500">Harga Min</span>
                <input name="harga_min" type="number" value="{{ $filters['harga_min'] ?? '' }}" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
            </label>
            <label class="grid gap-1">
                <span class="text-xs font-medium text-slate-500">Harga Max</span>
                <input name="harga_max" type="number" value="{{ $filters['harga_max'] ?? '' }}" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
            </label>
        </div>
        <div class="xl:col-span-4">
            <div class="mb-2 text-xs font-medium text-slate-500">Fasilitas</div>
            <div class="flex flex-wrap gap-2">
                @foreach($facilityFilters as $field => $label)
                    <label class="inline-flex items-center gap-2 rounded-md border border-slate-200 px-3 py-2 text-sm">
                        <input type="checkbox" name="{{ $field }}" value="1" @checked(! empty($filters[$field]))>
                        <span>{{ $label }}</span>
                    </label>
                @endforeach
            </div>
        </div>
        <div class="flex gap-2 xl:col-span-4">
            <button class="rounded-md bg-teal-700 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-800">Filter</button>
            <a href="{{ route('balikos.indeks-harga') }}" class="rounded-md border border-slate-200 px-4 py-2 text-sm font-semibold hover:bg-slate-50">Reset</a>
        </div>
    </form>

    <div class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[860px] text-left text-sm">
                <thead class="bg-slate-50 text-slate-500">
                    <tr>
                        <th class="px-5 py-3 font-medium">Kecamatan</th>
                        <th class="px-5 py-3 font-medium">Jumlah Kamar</th>
                        <th class="px-5 py-3 font-medium">Terisi</th>
                        <th class="px-5 py-3 font-medium">Okupansi</th>
                        <th class="px-5 py-3 font-medium">Harga Minimum</th>
                        <th class="px-5 py-3 font-medium">Harga Maksimum</th>
                        <th class="px-5 py-3 font-medium">Harga Rata-rata</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($rows as $row)
                        <tr>
                            <td class="px-5 py-3 font-medium">{{ $row->kecamatan }}</td>
                            <td class="px-5 py-3">{{ $row->jumlah_kamar }}</td>
                            <td class="px-5 py-3">{{ $row->kamar_terisi }}</td>
                            <td class="px-5 py-3">{{ $row->okupansi }}%</td>
                            <td class="px-5 py-3">Rp {{ number_format($row->harga_min, 0, ',', '.') }}</td>
                            <td class="px-5 py-3">Rp {{ number_format($row->harga_max, 0, ',', '.') }}</td>
                            <td class="px-5 py-3">Rp {{ number_format($row->harga_rata_rata, 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-5 py-8 text-center text-slate-500">Data indeks tidak ditemukan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
