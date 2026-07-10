@extends('layouts.dashboard', [
    'title' => 'Pantau Data Kos',
    'heading' => 'Pantau Data Kos',
    'subheading' => 'Monitoring kos, wilayah, kamar, harga, dan status'
])

@section('content')
    <form method="get" class="mb-4 grid gap-3 rounded-lg border border-slate-200 bg-white p-4 shadow-sm md:grid-cols-5">
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
        <label class="grid gap-1">
            <span class="text-xs font-medium text-slate-500">Status</span>
            <select name="status" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
                <option value="">Semua</option>
                @foreach(['aktif' => 'Aktif', 'nonaktif' => 'Nonaktif'] as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <div class="flex items-end gap-2">
            <button class="rounded-md bg-teal-700 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-800">Filter</button>
            <a href="{{ route('balikos.kos') }}" class="rounded-md border border-slate-200 px-4 py-2 text-sm font-semibold hover:bg-slate-50">Reset</a>
        </div>
    </form>

    <div class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[1000px] text-left text-sm">
                <thead class="bg-slate-50 text-slate-500">
                    <tr>
                        <th class="px-5 py-3 font-medium">Nama Kos</th>
                        <th class="px-5 py-3 font-medium">Pemilik</th>
                        <th class="px-5 py-3 font-medium">Alamat</th>
                        <th class="px-5 py-3 font-medium">Wilayah</th>
                        <th class="px-5 py-3 font-medium">Kamar</th>
                        <th class="px-5 py-3 font-medium">Terisi</th>
                        <th class="px-5 py-3 font-medium">Kosong</th>
                        <th class="px-5 py-3 font-medium">Harga Rata-rata</th>
                        <th class="px-5 py-3 font-medium">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($kos as $row)
                        <tr>
                            <td class="px-5 py-3 font-medium">{{ $row->nama_kos }}</td>
                            <td class="px-5 py-3">{{ $row->owner_name }}</td>
                            <td class="px-5 py-3">{{ $row->alamat }}</td>
                            <td class="px-5 py-3">{{ $row->kecamatan }} / {{ $row->desa ?? '-' }} / {{ $row->banjar ?? '-' }}</td>
                            <td class="px-5 py-3">{{ $row->jumlah_kamar }}</td>
                            <td class="px-5 py-3">{{ $row->kamar_terisi ?? 0 }}</td>
                            <td class="px-5 py-3">{{ $row->kamar_kosong ?? 0 }}</td>
                            <td class="px-5 py-3">Rp {{ number_format($row->harga_rata_rata ?? 0, 0, ',', '.') }}</td>
                            <td class="px-5 py-3"><span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-medium">{{ $row->status }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-5 py-8 text-center text-slate-500">Data tidak ditemukan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
