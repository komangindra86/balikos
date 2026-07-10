@extends('layouts.dashboard', [
    'title' => 'Penarikan Dana QRIS',
    'heading' => 'Penarikan Dana QRIS',
    'subheading' => 'Request penarikan saldo dari pemilik kos'
])

@php
    $rupiah = fn ($value) => 'Rp '.number_format((int) $value, 0, ',', '.');
    $statusClass = [
        'menunggu' => 'bg-amber-50 text-amber-700 border-amber-200',
        'diproses' => 'bg-blue-50 text-blue-700 border-blue-200',
        'selesai' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
        'ditolak' => 'bg-rose-50 text-rose-700 border-rose-200',
    ];
@endphp

@section('content')
    <div class="mb-6 grid gap-4 md:grid-cols-5">
        <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <div class="text-sm text-slate-500">Menunggu</div>
            <div class="mt-1 text-2xl font-semibold">{{ $summary['menunggu'] }}</div>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <div class="text-sm text-slate-500">Diproses</div>
            <div class="mt-1 text-2xl font-semibold">{{ $summary['diproses'] }}</div>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <div class="text-sm text-slate-500">Selesai</div>
            <div class="mt-1 text-2xl font-semibold">{{ $summary['selesai'] }}</div>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <div class="text-sm text-slate-500">Ditolak</div>
            <div class="mt-1 text-2xl font-semibold">{{ $summary['ditolak'] }}</div>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <div class="text-sm text-slate-500">Nominal perlu diproses</div>
            <div class="mt-1 text-xl font-semibold">{{ $rupiah($summary['nominal_menunggu']) }}</div>
        </div>
    </div>

    <div class="mb-4 flex flex-wrap gap-2 text-sm">
        @foreach([null => 'Semua', 'menunggu' => 'Menunggu', 'diproses' => 'Diproses', 'selesai' => 'Selesai', 'ditolak' => 'Ditolak'] as $key => $label)
            <a href="{{ route('balikos.penarikan', $key ? ['status' => $key] : []) }}"
               class="rounded-md border px-3 py-2 font-medium {{ ($status === $key || (!$status && !$key)) ? 'border-teal-200 bg-teal-50 text-teal-700' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-slate-500">
                <tr>
                    <th class="px-5 py-3">Pemilik / Kos</th>
                    <th class="px-5 py-3">Nominal</th>
                    <th class="px-5 py-3">Rekening Tujuan</th>
                    <th class="px-5 py-3">Status</th>
                    <th class="px-5 py-3">Aksi Admin</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($withdrawals as $item)
                    <tr class="align-top">
                        <td class="px-5 py-4">
                            <div class="font-semibold">{{ $item->owner_name }}</div>
                            <div class="text-slate-500">{{ $item->nama_kos }}</div>
                            <div class="text-xs text-slate-400">{{ $item->owner_email }} {{ $item->owner_phone ? ' / '.$item->owner_phone : '' }}</div>
                            <div class="mt-2 text-xs text-slate-400">Diajukan {{ \Illuminate\Support\Carbon::parse($item->created_at)->format('d M Y H:i') }}</div>
                        </td>
                        <td class="px-5 py-4 font-semibold">{{ $rupiah($item->nominal) }}</td>
                        <td class="px-5 py-4">
                            <div class="font-medium">{{ $item->nama_bank }}</div>
                            <div>{{ $item->nomor_rekening }}</div>
                            <div class="text-slate-500">a.n. {{ $item->atas_nama }}</div>
                            @if($item->catatan)
                                <div class="mt-2 rounded-md bg-slate-50 p-2 text-xs text-slate-500">{{ $item->catatan }}</div>
                            @endif
                        </td>
                        <td class="px-5 py-4">
                            <span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold {{ $statusClass[$item->status] ?? 'border-slate-200 bg-slate-50 text-slate-600' }}">
                                {{ ucfirst($item->status) }}
                            </span>
                        </td>
                        <td class="px-5 py-4">
                            @if($item->status !== 'selesai')
                                <form method="post" action="{{ route('balikos.penarikan.update', $item->id) }}" class="grid gap-2">
                                    @csrf
                                    @method('PUT')
                                    <select name="status" class="rounded-md border border-slate-200 px-3 py-2">
                                        <option value="diproses" @selected($item->status === 'diproses')>Diproses</option>
                                        <option value="selesai">Selesai</option>
                                        <option value="ditolak">Tolak dan kembalikan saldo</option>
                                    </select>
                                    <textarea name="catatan" rows="2" class="rounded-md border border-slate-200 px-3 py-2" placeholder="Catatan admin opsional">{{ $item->catatan }}</textarea>
                                    <button class="rounded-md bg-teal-600 px-3 py-2 font-semibold text-white hover:bg-teal-700">Simpan Status</button>
                                </form>
                            @else
                                <div class="text-slate-500">Penarikan selesai.</div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-5 py-10 text-center text-slate-500">Belum ada request penarikan dana.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
