<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Bali Santih' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-50 text-slate-900 antialiased">
    <div class="min-h-screen lg:flex">
        <aside class="border-b border-slate-200 bg-white lg:w-72 lg:border-b-0 lg:border-r">
            <div class="flex items-center justify-between px-5 py-4 lg:block">
                <div>
                    <div class="text-lg font-semibold">Bali Santih</div>
                    <div class="text-sm text-slate-500">BALIKOS</div>
                </div>
                <form method="post" action="{{ route('logout') }}">
                    @csrf
                    <button class="rounded-md border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Logout</button>
                </form>
            </div>
            <nav class="grid gap-1 px-3 pb-4 text-sm lg:pt-2">
                @php
                    $pendingWithdrawals = in_array($authUser->role, ['superadmin', 'admin_balikos'], true)
                        ? \Illuminate\Support\Facades\DB::table('kos_wallet_withdrawals')->where('status', 'menunggu')->count()
                        : 0;
                @endphp
                <a class="rounded-md px-3 py-2 font-medium {{ request()->routeIs('dashboard') ? 'bg-teal-50 text-teal-700' : 'text-slate-700 hover:bg-slate-100' }}" href="{{ route('dashboard') }}">Dashboard</a>
                <a class="rounded-md px-3 py-2 font-medium {{ request()->routeIs('balikos.index') ? 'bg-teal-50 text-teal-700' : 'text-slate-700 hover:bg-slate-100' }}" href="{{ route('balikos.index') }}">BALIKOS</a>
                @if(in_array($authUser->role, ['superadmin', 'admin_balikos'], true))
                    <a class="rounded-md px-3 py-2 font-medium {{ request()->routeIs('balikos.pemilik*') ? 'bg-teal-50 text-teal-700' : 'text-slate-700 hover:bg-slate-100' }}" href="{{ route('balikos.pemilik') }}">Pemilik Kos</a>
                @endif
                <a class="rounded-md px-3 py-2 font-medium {{ request()->routeIs('balikos.kos') ? 'bg-teal-50 text-teal-700' : 'text-slate-700 hover:bg-slate-100' }}" href="{{ route('balikos.kos') }}">Data Kos</a>
                <a class="rounded-md px-3 py-2 font-medium {{ request()->routeIs('balikos.indeks-harga') ? 'bg-teal-50 text-teal-700' : 'text-slate-700 hover:bg-slate-100' }}" href="{{ route('balikos.indeks-harga') }}">Indeks Harga</a>
                @if(in_array($authUser->role, ['superadmin', 'admin_balikos'], true))
                    <a class="rounded-md px-3 py-2 font-medium {{ request()->routeIs('balikos.laporan') ? 'bg-teal-50 text-teal-700' : 'text-slate-700 hover:bg-slate-100' }}" href="{{ route('balikos.laporan') }}">Laporan</a>
                    <a class="flex items-center justify-between rounded-md px-3 py-2 font-medium {{ request()->routeIs('balikos.penarikan') ? 'bg-teal-50 text-teal-700' : 'text-slate-700 hover:bg-slate-100' }}" href="{{ route('balikos.penarikan') }}">
                        <span>Penarikan Dana</span>
                        @if($pendingWithdrawals > 0)
                            <span class="rounded-full bg-rose-100 px-2 py-0.5 text-xs font-semibold text-rose-700">{{ $pendingWithdrawals }}</span>
                        @endif
                    </a>
                @endif
                <div class="mt-3 border-t border-slate-100 pt-3">
                    <div class="px-3 pb-1 text-xs font-semibold uppercase tracking-wide text-slate-400">Legal</div>
                    <a class="block rounded-md px-3 py-2 font-medium text-slate-700 hover:bg-slate-100" href="{{ route('privacy-policy') }}" target="_blank" rel="noopener">Kebijakan Privasi</a>
                    <a class="block rounded-md px-3 py-2 font-medium text-slate-700 hover:bg-slate-100" href="{{ route('account-deletion') }}" target="_blank" rel="noopener">Penghapusan Akun/Data</a>
                </div>
            </nav>
        </aside>

        <main class="flex-1">
            <header class="border-b border-slate-200 bg-white px-5 py-4">
                <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 class="text-xl font-semibold">{{ $heading ?? 'Dashboard' }}</h1>
                        <p class="text-sm text-slate-500">{{ $subheading ?? 'Fondasi module BALIKOS' }}</p>
                    </div>
                    <div class="text-sm text-slate-600">{{ $authUser->name }} - {{ str_replace('_', ' ', $authUser->role) }}</div>
                </div>
            </header>

            <section class="px-5 py-6">
                @if(session('success'))
                    <div class="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
                @endif
                @if(session('error'))
                    <div class="mb-4 rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ session('error') }}</div>
                @endif
                @yield('content')
            </section>
        </main>
    </div>
</body>
</html>
