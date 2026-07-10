<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login BALIKOS</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900">
    <main class="grid min-h-screen place-items-center px-4 py-10">
        <div class="w-full max-w-md rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <div class="mb-6">
                <div class="text-sm font-semibold uppercase tracking-wide text-teal-700">Bali Santih</div>
                <h1 class="mt-1 text-2xl font-semibold">Login BALIKOS</h1>
                <p class="mt-2 text-sm text-slate-500">Masuk sebagai superadmin, admin BALIKOS, atau pemilik kos.</p>
            </div>

            @if(session('success'))
                <div class="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="mb-4 rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ session('error') }}</div>
            @endif

            <form method="post" action="{{ route('login.store') }}" class="grid gap-4">
                @csrf
                <label class="grid gap-1">
                    <span class="text-sm font-medium">Email</span>
                    <input name="email" type="email" value="{{ old('email') }}" class="rounded-md border border-slate-300 px-3 py-2 outline-none focus:border-teal-600 focus:ring-2 focus:ring-teal-100" required autofocus>
                    @error('email') <span class="text-sm text-rose-600">{{ $message }}</span> @enderror
                </label>
                <label class="grid gap-1">
                    <span class="text-sm font-medium">Password</span>
                    <input name="password" type="password" class="rounded-md border border-slate-300 px-3 py-2 outline-none focus:border-teal-600 focus:ring-2 focus:ring-teal-100" required>
                    @error('password') <span class="text-sm text-rose-600">{{ $message }}</span> @enderror
                </label>
                <button class="rounded-md bg-teal-700 px-4 py-2.5 font-semibold text-white hover:bg-teal-800">Login</button>
            </form>
        </div>
    </main>
</body>
</html>
