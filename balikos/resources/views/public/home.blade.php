<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Temukan kos terverifikasi di Bali lengkap dengan harga, fasilitas, lokasi, dan kontak pemilik.">
    <title>BALIKOS — Cari Kos Nyaman di Bali</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[#f7f8f4] text-[#17231d] antialiased">
<header class="sticky top-0 z-40 border-b border-[#dfe5dd]/80 bg-[#f7f8f4]/90 backdrop-blur-xl">
    <div class="mx-auto flex h-18 max-w-7xl items-center justify-between px-5 lg:px-8">
        <a href="{{ route('home') }}" class="flex items-center gap-2.5" aria-label="BALIKOS Beranda">
            <span class="grid size-9 place-items-center rounded-xl bg-[#166044] text-lg font-extrabold text-white">B</span>
            <span class="text-xl font-extrabold tracking-tight">BALI<span class="text-[#e9693d]">KOS</span></span>
        </a>
        <nav class="hidden items-center gap-8 text-sm font-semibold md:flex">
            <a href="#daftar-kos" class="hover:text-[#166044]">Cari kos</a>
            <a href="#cara-kerja" class="hover:text-[#166044]">Cara kerja</a>
            <a href="#keamanan" class="hover:text-[#166044]">Keamanan</a>
        </nav>
        <button type="button" id="owner-app-button" class="rounded-xl border border-[#cad5ce] bg-white px-4 py-2.5 text-sm font-bold shadow-sm transition hover:border-[#166044] hover:text-[#166044]">Aplikasi pemilik</button>
    </div>
</header>

<main>
    <section class="relative overflow-hidden border-b border-[#e1e7e2] bg-[#eef4ee]">
        <div class="absolute -right-32 -top-32 size-96 rounded-full bg-[#dbe9db]"></div>
        <div class="absolute -bottom-24 left-1/4 size-64 rounded-full bg-[#f4d8c8]/60 blur-2xl"></div>
        <div class="relative mx-auto max-w-7xl px-5 pb-16 pt-14 lg:px-8 lg:pb-22 lg:pt-20">
            <div class="max-w-3xl">
                <div class="mb-5 inline-flex items-center gap-2 rounded-full border border-[#c9dacd] bg-white/75 px-3 py-1.5 text-xs font-bold text-[#166044]">
                    <span class="size-2 rounded-full bg-[#2ba36f]"></span> Data kos dari pemilik terdaftar
                </div>
                <h1 class="text-4xl font-extrabold leading-[1.12] tracking-[-0.04em] sm:text-5xl lg:text-6xl">Kos nyaman di Bali,<br><span class="text-[#166044]">tanpa rasa ragu.</span></h1>
                <p class="mt-5 max-w-2xl text-base leading-7 text-[#5c6c63] sm:text-lg">Bandingkan harga, fasilitas, dan lokasi dari kos yang sudah terdaftar. Hubungi pemilik langsung saat kamu menemukan yang cocok.</p>
            </div>

            <form action="{{ route('home') }}" method="GET" class="mt-9 rounded-2xl border border-white bg-white p-3 shadow-[0_16px_50px_rgba(38,69,51,.12)] lg:flex lg:items-center lg:gap-2">
                <label class="flex min-w-0 flex-1 items-center gap-3 px-3 py-2">
                    <span class="text-xl">⌕</span>
                    <span class="min-w-0 flex-1"><span class="block text-xs font-bold text-[#738077]">Nama atau area kos</span><input name="q" value="{{ request('q') }}" placeholder="Contoh: Renon, Ubud, Jimbaran" class="w-full bg-transparent text-sm font-semibold outline-none placeholder:font-normal placeholder:text-[#a2aaa5]"></span>
                </label>
                <div class="my-1 h-px bg-[#e7ebe8] lg:h-10 lg:w-px"></div>
                <label class="flex min-w-0 flex-1 items-center gap-3 px-3 py-2">
                    <span class="text-lg">●</span>
                    <span class="min-w-0 flex-1"><span class="block text-xs font-bold text-[#738077]">Lokasi</span><select name="lokasi" class="w-full bg-transparent text-sm font-semibold outline-none"><option value="">Semua wilayah</option>@foreach($locations as $location)<option value="{{ $location }}" @selected(request('lokasi') === $location)>{{ $location }}</option>@endforeach</select></span>
                </label>
                <button class="mt-2 w-full rounded-xl bg-[#166044] px-7 py-4 text-sm font-extrabold text-white shadow-lg shadow-[#166044]/15 transition hover:bg-[#0e4e36] lg:mt-0 lg:w-auto">Cari kos</button>
            </form>
            <div class="mt-5 flex flex-wrap gap-x-6 gap-y-2 text-xs font-semibold text-[#66766c]"><span>✓ Tanpa biaya pencarian</span><span>✓ Kontak pemilik langsung</span><span>✓ Harga transparan</span></div>
        </div>
    </section>

    <section id="daftar-kos" class="mx-auto max-w-7xl px-5 py-14 lg:px-8 lg:py-20">
        <div class="flex flex-col gap-8 lg:flex-row lg:items-start">
            <aside class="lg:sticky lg:top-24 lg:w-64 lg:shrink-0">
                <button type="button" id="filter-toggle" class="flex w-full items-center justify-between rounded-xl border bg-white p-4 font-bold lg:hidden"><span>Filter pencarian</span><span>＋</span></button>
                <form id="filters" class="mt-3 hidden rounded-2xl border border-[#e0e5e1] bg-white p-5 lg:block" action="{{ route('home') }}">
                    <input type="hidden" name="q" value="{{ request('q') }}"><input type="hidden" name="lokasi" value="{{ request('lokasi') }}">
                    <div class="flex items-center justify-between"><h2 class="font-extrabold">Filter</h2><a href="{{ route('home') }}#daftar-kos" class="text-xs font-bold text-[#e15f35]">Reset</a></div>
                    <div class="mt-6 border-t border-[#edf0ed] pt-5"><p class="text-sm font-bold">Harga per bulan</p><div class="mt-3 grid grid-cols-2 gap-2"><input type="number" name="harga_min" value="{{ request('harga_min') }}" placeholder="Min" class="min-w-0 rounded-lg border border-[#dce2de] px-3 py-2.5 text-xs outline-none focus:border-[#166044]"><input type="number" name="harga_max" value="{{ request('harga_max') }}" placeholder="Maks" class="min-w-0 rounded-lg border border-[#dce2de] px-3 py-2.5 text-xs outline-none focus:border-[#166044]"></div></div>
                    <div class="mt-6 border-t border-[#edf0ed] pt-5"><p class="text-sm font-bold">Fasilitas</p><div class="mt-3 space-y-3 text-sm text-[#526158]">
                        @foreach(['ac'=>'AC','km_dalam'=>'Kamar mandi dalam','wifi'=>'Wi-Fi','kasur'=>'Kasur','lemari'=>'Lemari','meja'=>'Meja belajar','parkir'=>'Area parkir','dapur_dalam'=>'Dapur dalam'] as $key=>$label)
                            <label class="flex cursor-pointer items-center gap-3"><input type="checkbox" name="fasilitas[]" value="{{ $key }}" @checked(in_array($key, $facilities)) class="size-4 accent-[#166044]">{{ $label }}</label>
                        @endforeach
                    </div></div>
                    <button class="mt-6 w-full rounded-xl bg-[#17231d] py-3 text-sm font-bold text-white">Terapkan filter</button>
                </form>
            </aside>

            <div class="min-w-0 flex-1">
                <div class="mb-7 flex items-end justify-between gap-4"><div><p class="text-xs font-bold uppercase tracking-[.16em] text-[#e15f35]">Pilihan tersedia</p><h2 class="mt-1 text-2xl font-extrabold tracking-tight sm:text-3xl">Temukan tempat pulangmu</h2></div><p class="shrink-0 text-sm text-[#718078]"><strong class="text-[#17231d]">{{ $kos->total() }}</strong> kos ditemukan</p></div>

                @if($kos->count())
                <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
                    @foreach($kos as $item)
                    @php
                        $wa = preg_replace('/\D/', '', $item->no_wa ?? ''); if(str_starts_with($wa, '0')) $wa = '62'.substr($wa, 1);
                        $features = array_filter(['AC'=>$item->fasilitas_ac,'KM dalam'=>$item->fasilitas_km_dalam,'Wi-Fi'=>$item->fasilitas_wifi,'Kasur'=>$item->fasilitas_kasur,'Lemari'=>$item->fasilitas_lemari,'Meja'=>$item->fasilitas_meja,'Parkir'=>$item->fasilitas_parkir,'Dapur'=>$item->fasilitas_dapur_dalam]);
                        $maps = $item->latitude && $item->longitude ? 'https://www.google.com/maps?q='.$item->latitude.','.$item->longitude : 'https://www.google.com/maps/search/?api=1&query='.urlencode($item->alamat);
                    @endphp
                    <article class="group overflow-hidden rounded-2xl border border-[#dfe5e0] bg-white shadow-[0_4px_20px_rgba(34,55,43,.05)] transition hover:-translate-y-1 hover:shadow-[0_14px_35px_rgba(34,55,43,.12)]">
                        <div class="relative aspect-[4/3] overflow-hidden bg-gradient-to-br from-[#dfeae1] to-[#f0dcd0]">
                            @if($item->foto)<img src="{{ route('balikos.media', ['path'=>$item->foto]) }}" alt="Foto {{ $item->nama_kos }}" class="size-full object-cover transition duration-500 group-hover:scale-105">@else<div class="grid size-full place-items-center text-center text-[#6b8174]"><div><div class="text-5xl">⌂</div><span class="mt-2 block text-xs font-bold">Foto segera tersedia</span></div></div>@endif
                            <span class="absolute left-3 top-3 rounded-full bg-white/95 px-3 py-1.5 text-[11px] font-extrabold text-[#166044] shadow-sm">● {{ $item->kamar_tersedia }} kamar tersedia</span>
                        </div>
                        <div class="p-5"><div class="flex items-start justify-between gap-3"><div><h3 class="line-clamp-1 text-lg font-extrabold">{{ $item->nama_kos }}</h3><p class="mt-1 line-clamp-1 text-xs text-[#718078]">{{ $item->desa ? $item->desa.', ' : '' }}{{ $item->kecamatan }}</p></div><span class="shrink-0 rounded-lg bg-[#edf5ef] px-2 py-1 text-[10px] font-bold text-[#166044]">Terverifikasi</span></div>
                            <div class="mt-4 flex flex-wrap gap-1.5">@foreach(array_slice(array_keys($features),0,4) as $feature)<span class="rounded-md bg-[#f1f3f0] px-2 py-1 text-[10px] font-semibold text-[#59675f]">{{ $feature }}</span>@endforeach @if(count($features)>4)<span class="rounded-md bg-[#f1f3f0] px-2 py-1 text-[10px] font-semibold">+{{ count($features)-4 }}</span>@endif</div>
                            <div class="mt-5 border-t border-[#edf0ed] pt-4"><p class="text-[11px] text-[#7b877f]">Mulai dari</p><p class="text-lg font-extrabold text-[#166044]">Rp {{ number_format($item->harga_mulai, 0, ',', '.') }}<span class="text-xs font-medium text-[#7b877f]"> /bulan</span></p></div>
                            <button type="button" class="detail-btn mt-4 w-full rounded-xl border border-[#ccd6d0] py-2.5 text-sm font-extrabold transition hover:border-[#166044] hover:bg-[#edf5ef] hover:text-[#166044]" data-id="detail-{{ $item->id }}">Lihat detail</button>
                        </div>
                        <dialog id="detail-{{ $item->id }}" class="m-auto w-[calc(100%-2rem)] max-w-2xl rounded-3xl bg-white p-0 shadow-2xl backdrop:bg-[#102219]/60 backdrop:backdrop-blur-sm">
                            <div class="relative"><button class="dialog-close absolute right-4 top-4 z-10 grid size-10 place-items-center rounded-full bg-white text-xl shadow-md" aria-label="Tutup">×</button><div class="aspect-[16/7] bg-[#e4ebe4]">@if($item->foto)<img src="{{ route('balikos.media', ['path'=>$item->foto]) }}" alt="{{ $item->nama_kos }}" class="size-full object-cover">@endif</div><div class="p-6 sm:p-8"><div class="flex flex-wrap items-start justify-between gap-4"><div><span class="text-xs font-bold text-[#166044]">✓ Pemilik terdaftar</span><h3 class="mt-1 text-2xl font-extrabold">{{ $item->nama_kos }}</h3><p class="mt-2 text-sm leading-6 text-[#647269]">{{ $item->alamat }}</p></div><div><p class="text-xs text-[#79857e]">Mulai</p><strong class="text-xl text-[#166044]">Rp {{ number_format($item->harga_mulai,0,',','.') }}</strong><span class="text-xs">/bulan</span></div></div><div class="mt-6"><h4 class="text-sm font-extrabold">Fasilitas tersedia</h4><div class="mt-3 flex flex-wrap gap-2">@foreach(array_keys($features) as $feature)<span class="rounded-lg bg-[#edf5ef] px-3 py-2 text-xs font-semibold">✓ {{ $feature }}</span>@endforeach</div></div>@if($item->aturan_kos)<div class="mt-6 rounded-xl bg-[#f7f8f4] p-4"><h4 class="text-sm font-extrabold">Aturan kos</h4><p class="mt-2 whitespace-pre-line text-sm leading-6 text-[#647269]">{{ $item->aturan_kos }}</p></div>@endif<div class="mt-7 grid gap-3 sm:grid-cols-2"><a target="_blank" rel="noopener" href="{{ $maps }}" class="rounded-xl border border-[#cad4ce] py-3 text-center text-sm font-bold">Lihat lokasi</a>@if($wa)<a target="_blank" rel="noopener" href="https://wa.me/{{ $wa }}?text={{ urlencode('Halo, saya melihat '.$item->nama_kos.' di BALIKOS. Apakah kamar masih tersedia?') }}" class="rounded-xl bg-[#166044] py-3 text-center text-sm font-bold text-white">Hubungi pemilik via WhatsApp</a>@else<span class="rounded-xl bg-[#e9eeea] py-3 text-center text-sm text-[#758078]">Kontak belum tersedia</span>@endif</div></div></div>
                        </dialog>
                    </article>
                    @endforeach
                </div>
                <div class="mt-10">{{ $kos->links() }}</div>
                @else
                <div class="rounded-3xl border border-dashed border-[#cbd5ce] bg-white px-6 py-16 text-center"><div class="text-5xl">⌕</div><h3 class="mt-4 text-xl font-extrabold">Belum ada kos yang cocok</h3><p class="mt-2 text-sm text-[#6f7c74]">Coba ubah lokasi, rentang harga, atau kurangi pilihan fasilitas.</p><a href="{{ route('home') }}#daftar-kos" class="mt-5 inline-block rounded-xl bg-[#166044] px-5 py-3 text-sm font-bold text-white">Reset pencarian</a></div>
                @endif
            </div>
        </div>
    </section>

    <section id="keamanan" class="bg-[#163d2e] text-white"><div class="mx-auto grid max-w-7xl gap-10 px-5 py-16 lg:grid-cols-[1fr_1.4fr] lg:px-8 lg:py-20"><div><p class="text-xs font-bold uppercase tracking-[.16em] text-[#91c5a7]">Lebih tenang mencari</p><h2 class="mt-3 text-3xl font-extrabold tracking-tight">Informasi jelas sebelum kamu menghubungi.</h2><p class="mt-4 text-sm leading-7 text-[#b9cfc3]">BALIKOS menampilkan informasi dari data operasional pemilik kos yang terdaftar di platform.</p></div><div class="grid gap-4 sm:grid-cols-3"><div class="rounded-2xl bg-white/8 p-5"><span class="text-2xl">✓</span><h3 class="mt-4 font-bold">Pemilik terdaftar</h3><p class="mt-2 text-xs leading-5 text-[#b9cfc3]">Identitas pengelola tercatat di sistem.</p></div><div class="rounded-2xl bg-white/8 p-5"><span class="text-2xl">◎</span><h3 class="mt-4 font-bold">Harga transparan</h3><p class="mt-2 text-xs leading-5 text-[#b9cfc3]">Harga kamar tampil sebelum menghubungi.</p></div><div class="rounded-2xl bg-white/8 p-5"><span class="text-2xl">⌖</span><h3 class="mt-4 font-bold">Lokasi jelas</h3><p class="mt-2 text-xs leading-5 text-[#b9cfc3]">Alamat dan petunjuk lokasi mudah diperiksa.</p></div></div></div></section>
    <section id="cara-kerja" class="mx-auto max-w-7xl px-5 py-16 lg:px-8"><div class="text-center"><p class="text-xs font-bold uppercase tracking-[.16em] text-[#e15f35]">Mudah dan langsung</p><h2 class="mt-2 text-3xl font-extrabold">Tiga langkah menuju kos pilihan</h2></div><div class="mx-auto mt-10 grid max-w-4xl gap-5 sm:grid-cols-3">@foreach([['01','Cari & saring','Pilih area, harga, dan fasilitas yang kamu perlukan.'],['02','Bandingkan','Cek foto, harga, fasilitas, dan ketersediaan kamar.'],['03','Hubungi pemilik','Tanya dan atur jadwal survei langsung lewat WhatsApp.']] as $step)<div class="rounded-2xl border border-[#dfe5e0] bg-white p-6"><span class="text-xs font-extrabold text-[#e15f35]">{{ $step[0] }}</span><h3 class="mt-4 font-extrabold">{{ $step[1] }}</h3><p class="mt-2 text-sm leading-6 text-[#6c7971]">{{ $step[2] }}</p></div>@endforeach</div></section>
</main>
<footer class="border-t border-[#dfe5e0] bg-white"><div class="mx-auto flex max-w-7xl flex-col gap-5 px-5 py-8 text-sm text-[#69766e] sm:flex-row sm:items-center sm:justify-between lg:px-8"><div><strong class="text-[#17231d]">BALIKOS</strong><span class="ml-2">Cari kos nyaman di Bali.</span></div><div class="flex gap-5"><a href="{{ route('privacy-policy') }}">Kebijakan Privasi</a><a href="{{ route('login') }}">Admin platform</a></div></div></footer>
<dialog id="owner-app-dialog" class="m-auto w-[calc(100%-2rem)] max-w-md rounded-3xl bg-white p-0 shadow-2xl backdrop:bg-[#102219]/60 backdrop:backdrop-blur-sm">
    <div class="p-7 text-center sm:p-9">
        <div class="mx-auto grid size-16 place-items-center rounded-2xl bg-[#edf5ef] text-3xl">⌂</div>
        <p class="mt-5 text-xs font-extrabold uppercase tracking-[.14em] text-[#e15f35]">Segera hadir</p>
        <h2 class="mt-2 text-2xl font-extrabold tracking-tight">Aplikasi BALIKOS Pemilik</h2>
        <p class="mt-3 text-sm leading-6 text-[#69766e]">Aplikasi untuk pemilik kos sedang kami siapkan dan belum tersedia di Google Play Store.</p>
        <button type="button" id="owner-app-close" class="mt-6 w-full rounded-xl bg-[#166044] py-3 text-sm font-bold text-white">Mengerti</button>
    </div>
</dialog>
<script>
document.getElementById('filter-toggle')?.addEventListener('click', () => document.getElementById('filters').classList.toggle('hidden'));
document.querySelectorAll('.detail-btn').forEach(btn => btn.addEventListener('click', () => document.getElementById(btn.dataset.id).showModal()));
document.querySelectorAll('.dialog-close').forEach(btn => btn.addEventListener('click', () => btn.closest('dialog').close()));
document.querySelectorAll('dialog').forEach(d => d.addEventListener('click', e => { if(e.target === d) d.close(); }));
const ownerAppDialog = document.getElementById('owner-app-dialog');
document.getElementById('owner-app-button')?.addEventListener('click', () => ownerAppDialog.showModal());
document.getElementById('owner-app-close')?.addEventListener('click', () => ownerAppDialog.close());
</script>
</body>
</html>
