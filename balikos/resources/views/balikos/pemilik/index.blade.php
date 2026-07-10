@extends('layouts.dashboard', [
    'title' => 'Pemilik Kos',
    'heading' => 'Pemilik Kos',
    'subheading' => 'Pantau akun pemilik kos BALIKOS'
])

@section('content')
    <div class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[900px] text-left text-sm">
                <thead class="bg-slate-50 text-slate-500">
                    <tr>
                        <th class="px-5 py-3 font-medium">Nama</th>
                        <th class="px-5 py-3 font-medium">Email</th>
                        <th class="px-5 py-3 font-medium">Telepon</th>
                        <th class="px-5 py-3 font-medium">Total Kos</th>
                        <th class="px-5 py-3 font-medium">Status</th>
                        <th class="px-5 py-3 font-medium">Detail</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($owners as $owner)
                        <tr>
                            <td class="px-5 py-3 font-medium">{{ $owner->name }}</td>
                            <td class="px-5 py-3">{{ $owner->email }}</td>
                            <td class="px-5 py-3">{{ $owner->phone ?? '-' }}</td>
                            <td class="px-5 py-3">{{ $owner->total_kos }}</td>
                            <td class="px-5 py-3"><span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-medium">{{ $owner->status }}</span></td>
                            <td class="px-5 py-3">
                                <a class="rounded-md border border-slate-200 px-3 py-1.5 font-medium hover:bg-slate-50" href="{{ route('balikos.pemilik.show', $owner->id) }}">Lihat</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
