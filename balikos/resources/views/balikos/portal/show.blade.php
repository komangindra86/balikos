@php
    $activeMethod = $paymentMethods->first();
    $isQris = $activeMethod && ($activeMethod->jenis === 'qris' || $activeMethod->verification_mode === 'automatic');
    $unpaidStatuses = ['belum_lunas', 'terlambat', 'ditolak'];
    $statusLabels = [
        'belum_lunas' => 'Belum bayar',
        'menunggu_verifikasi' => 'Menunggu dicek',
        'lunas' => 'Lunas',
        'ditolak' => 'Bukti ditolak',
        'terlambat' => 'Terlambat',
    ];
    $monthNames = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    $rupiah = fn ($value) => 'Rp '.number_format((int) $value, 0, ',', '.');
    $nextDueBill = $tagihan->first(fn ($bill) => in_array($bill->status, $unpaidStatuses, true));
    $manualUploadBill = (! $isQris) ? $nextDueBill : null;
    $nextDuePayload = $nextDueBill ? [
        'month' => $monthNames[(int) $nextDueBill->bulan] ?? $nextDueBill->bulan,
        'year' => $nextDueBill->tahun,
        'due' => $nextDueBill->tanggal_jatuh_tempo,
        'amount' => $rupiah($nextDueBill->nominal),
        'total' => $rupiah($nextDueBill->total_dibayar ?? ($nextDueBill->nominal + ($nextDueBill->biaya_platform ?? 0))),
    ] : null;
@endphp
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0f766e">
    <title>Portal Penghuni - {{ $kos->nama_kos ?? 'BALIKOS' }}</title>
    <link rel="manifest" href="{{ route('balikos.portal.manifest', $penghuni->portal_token) }}">
    <link rel="icon" href="/balikos-portal-icon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/balikos-portal-icon.svg">
    <style>
        :root { color-scheme: light; --bg:#f6f8f7; --surface:#fff; --text:#17211f; --muted:#64746f; --line:#d9e4e1; --teal:#0f766e; --soft:#eef7f5; --danger:#dc5f5f; --success:#228b63; }
        * { box-sizing: border-box; }
        body { margin:0; font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background:var(--bg); color:var(--text); }
        .wrap { max-width: 760px; margin:0 auto; padding:24px 16px 40px; }
        .hero { background:linear-gradient(135deg,#fff,#eef7f5); border:1px solid var(--line); border-radius:24px; padding:22px; margin-bottom:16px; }
        .eyebrow { color:var(--teal); font-size:12px; font-weight:800; letter-spacing:.12em; text-transform:uppercase; }
        h1 { margin:6px 0 4px; font-size:28px; line-height:1.15; }
        h2 { margin:0 0 12px; font-size:20px; }
        .muted { color:var(--muted); line-height:1.55; }
        .card { background:var(--surface); border:1px solid var(--line); border-radius:18px; padding:16px; margin-bottom:14px; }
        .grid { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
        .pill { display:inline-flex; align-items:center; border-radius:999px; padding:6px 10px; font-size:12px; font-weight:800; background:var(--soft); color:var(--teal); }
        .status-lunas { color:var(--success); }
        .status-ditolak, .status-terlambat { color:var(--danger); }
        .amount { font-size:24px; font-weight:900; margin:6px 0; color:var(--teal); }
        .row { display:flex; justify-content:space-between; gap:12px; align-items:flex-start; }
        .button { width:100%; border:0; border-radius:14px; background:var(--teal); color:white; min-height:48px; padding:12px 16px; font-weight:900; font-size:15px; cursor:pointer; }
        .button.light { background:var(--soft); color:var(--teal); border:1px solid var(--line); }
        .secondary { display:block; text-align:center; border:1px solid var(--line); background:white; color:var(--teal); text-decoration:none; border-radius:14px; padding:12px 16px; font-weight:900; }
        input[type=file], input[type=date] { width:100%; border:1px solid var(--line); border-radius:14px; min-height:48px; padding:12px; background:white; margin:8px 0 12px; }
        .success { background:#e8f7ef; border-color:#bfe7d0; color:#136d47; }
        .error { background:#fff1f1; border-color:#efb7b7; color:#9f2f2f; }
        .warning { background:#fff8e8; border-color:#f1d38b; }
        .paybox { background:var(--soft); border:1px solid var(--line); border-radius:16px; padding:14px; margin-top:12px; }
        .note-box { background:var(--soft); border:1px solid var(--line); border-radius:16px; padding:12px; margin-top:12px; }
        .note-box strong { display:block; margin-bottom:4px; }
        .actions { display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-top:14px; }
        .announcement { border-left:4px solid var(--teal); }
        .hidden { display:none; }
        @media (max-width: 560px) { .grid { grid-template-columns:1fr; } h1 { font-size:24px; } .wrap { padding:16px 12px 32px; } }
        @media (max-width: 420px) { .actions { grid-template-columns:1fr; } }
    </style>
</head>
<body>
    <main class="wrap">
        <section class="hero">
            <div class="eyebrow">Portal Penghuni BALIKOS</div>
            <h1>{{ $kos->nama_kos ?? 'Kos' }}</h1>
            <div class="muted">Halo {{ $penghuni->nama_lengkap }}. Di halaman ini kamu bisa melihat tagihan dan cara pembayaran kos.</div>
            <div class="actions">
                <button class="button light" id="installButton" type="button">Install Portal</button>
                <button class="button light" id="notifyButton" type="button">Aktifkan Pengingat</button>
            </div>
        </section>

        @if (session('success'))
            <div class="card success">{{ session('success') }}</div>
        @endif

        @if (session('error'))
            <div class="card error">{{ session('error') }}</div>
        @endif

        @if ($announcements->isNotEmpty())
            <section class="card">
                <h2>Info dari Pemilik Kos</h2>
                @foreach ($announcements as $announcement)
                    <div class="card announcement">
                        <strong>{{ $announcement->judul }}</strong>
                        <div class="muted">{{ $announcement->isi }}</div>
                    </div>
                @endforeach
            </section>
        @endif

        <section class="grid">
            <div class="card">
                <h2>Data Kamar</h2>
                <div class="muted">Kamar {{ $kamar->nomor_kamar ?? '-' }}</div>
                <div class="muted">Masuk {{ $penghuni->tanggal_masuk ?? '-' }}</div>
                @if (! empty($kamar->catatan))
                    <div class="note-box">
                        <strong>Catatan kamar</strong>
                        <div class="muted">{{ $kamar->catatan }}</div>
                    </div>
                @endif
            </div>
            <div class="card">
                <h2>Metode Bayar</h2>
                @if ($activeMethod)
                    <span class="pill">{{ $isQris ? 'QRIS Otomatis' : 'Transfer Bank Manual' }}</span>
                    @if ($isQris)
                        <p class="muted">Scan QRIS untuk membayar. Setelah pembayaran berhasil, tagihan akan otomatis tercatat lunas.</p>
                    @else
                        <p class="muted">Transfer ke {{ $activeMethod->nama_bank }} {{ $activeMethod->nomor_rekening }} a.n. {{ $activeMethod->atas_nama }}. Setelah transfer, upload bukti pada tagihan yang dibayar.</p>
                    @endif
                @else
                    <p class="muted">Metode pembayaran belum diatur pemilik kos.</p>
                @endif
            </div>
        </section>

        <section class="card">
            <h2>Pembayaran / Upload Bukti</h2>
            @if (! $activeMethod)
                <p class="muted">Metode pembayaran belum diatur pemilik kos. Silakan hubungi pemilik kos.</p>
            @elseif ($isQris)
                <p class="muted">Kos ini memakai QRIS otomatis. Penghuni cukup membayar dengan QRIS, lalu tagihan akan tercatat lunas setelah pembayaran berhasil. Tidak perlu upload bukti manual.</p>
                @if ($nextDueBill)
                    <div class="paybox">
                        <strong>Total bayar QRIS</strong>
                        <p class="muted">
                            Tagihan {{ $rupiah($nextDueBill->nominal) }}
                            @if (($nextDueBill->nominal_terbayar ?? 0) > 0)
                                , sudah dibayar {{ $rupiah($nextDueBill->nominal_terbayar) }}, sisa {{ $rupiah($nextDueBill->sisa_tagihan ?? max(0, $nextDueBill->nominal - $nextDueBill->nominal_terbayar)) }}
                            @endif
                            + biaya layanan 1% {{ $rupiah($nextDueBill->biaya_platform ?? 0) }}.
                        </p>
                        <div class="amount">{{ $rupiah($nextDueBill->total_dibayar ?? ($nextDueBill->nominal + ($nextDueBill->biaya_platform ?? 0))) }}</div>
                    </div>
                @endif
                @if ($nextDueBill && ! empty($nextDueBill->qris_payment_url))
                    <a class="secondary" href="{{ $nextDueBill->qris_payment_url }}" target="_blank" rel="noopener">Bayar dengan QRIS</a>
                @endif
            @elseif ($manualUploadBill)
                <p class="muted">Untuk tagihan {{ $monthNames[(int) $manualUploadBill->bulan] ?? $manualUploadBill->bulan }} {{ $manualUploadBill->tahun }} sebesar {{ $rupiah($manualUploadBill->nominal) }}.</p>
                <div class="paybox">
                    <strong>Transfer ke rekening pemilik kos</strong>
                    <p class="muted">{{ $activeMethod->nama_bank }} {{ $activeMethod->nomor_rekening }} a.n. {{ $activeMethod->atas_nama }}</p>
                    <form method="post" action="{{ route('balikos.portal.upload-proof', [$penghuni->portal_token, $manualUploadBill->id]) }}" enctype="multipart/form-data">
                        @csrf
                        <input type="hidden" name="metode_pembayaran" value="transfer">
                        <label class="muted">Tanggal bayar</label>
                        <input type="date" name="tanggal_bayar" value="{{ now()->toDateString() }}">
                        <label class="muted">Foto bukti transfer</label>
                        <input type="file" name="bukti_pembayaran" accept="image/*" required>
                        <button class="button" type="submit">Kirim Bukti Bayar</button>
                    </form>
                </div>
            @else
                <p class="muted">Belum ada tagihan yang perlu dibayar saat ini. Jika sudah membayar dan status masih menunggu dicek, pemilik kos sedang memverifikasi pembayaran.</p>
            @endif
        </section>

        <section class="card" id="installSection">
            <h2>Install Portal</h2>
            <p class="muted">Tekan tombol Install Portal di bagian atas. Jika browser tidak membuka jendela install, buka menu browser lalu pilih “Install app”, “Add to Home screen”, atau “Tambahkan ke layar utama”.</p>
        </section>

        <section class="card">
            <h2>Tagihan</h2>
            @forelse ($tagihan as $bill)
                <div class="card bill-card">
                    <div class="row">
                        <div>
                            <strong>{{ $monthNames[(int) $bill->bulan] ?? $bill->bulan }} {{ $bill->tahun }}</strong>
                            <div class="muted">Jatuh tempo {{ $bill->tanggal_jatuh_tempo ?? '-' }}</div>
                        </div>
                        <span class="pill status-{{ $bill->status }}">{{ $statusLabels[$bill->status] ?? $bill->status }}</span>
                    </div>
                    <div class="amount">{{ $rupiah($bill->nominal) }}</div>
                    @if (($bill->nominal_terbayar ?? 0) > 0 && $bill->status !== 'lunas')
                        <div class="muted">Sudah dibayar {{ $rupiah($bill->nominal_terbayar) }}. Sisa tagihan {{ $rupiah($bill->sisa_tagihan ?? max(0, $bill->nominal - $bill->nominal_terbayar)) }}.</div>
                    @endif
                    @if ($activeMethod && $isQris && in_array($bill->status, $unpaidStatuses, true))
                        <div class="muted">Biaya layanan QRIS 1%: {{ $rupiah($bill->biaya_platform ?? 0) }}</div>
                        <div class="muted"><strong>Total dibayar: {{ $rupiah($bill->total_dibayar ?? ($bill->nominal + ($bill->biaya_platform ?? 0))) }}</strong></div>
                    @endif

                    @if ($activeMethod && $isQris && in_array($bill->status, $unpaidStatuses, true))
                        @if (! empty($bill->qris_payment_url))
                            <a class="secondary" href="{{ $bill->qris_payment_url }}" target="_blank" rel="noopener">Bayar dengan QRIS</a>
                        @else
                            <div class="card warning muted">QRIS pembayaran sedang disiapkan. Silakan hubungi pemilik kos jika tombol bayar belum muncul.</div>
                        @endif
                    @endif

                    @if (! $activeMethod && in_array($bill->status, $unpaidStatuses, true))
                        <div class="card warning muted">Metode pembayaran belum tersedia. Silakan hubungi pemilik kos.</div>
                    @endif

                    @if ($bill->status === 'menunggu_verifikasi')
                        <p class="muted">Bukti pembayaran sudah dikirim dan sedang dicek pemilik kos.</p>
                    @endif
                    @if ($bill->status === 'ditolak' && $bill->alasan_penolakan)
                        <p class="muted">Catatan pemilik: {{ $bill->alasan_penolakan }}</p>
                    @endif
                </div>
            @empty
                <p class="muted">Belum ada tagihan.</p>
            @endforelse
            @if ($tagihan->count() > 5)
                <button class="button light" id="loadMoreBills" type="button">Tampilkan 5 tagihan lagi</button>
            @endif
        </section>
    </main>
    <script>
        const portalName = @json('Portal '.$penghuni->nama_lengkap);
        const nextDueBill = @json($nextDuePayload);
        const statusUrl = @json(route('balikos.portal.status', $penghuni->portal_token));
        let deferredInstallPrompt = null;
        let lastPortalStatus = null;
        const installButton = document.getElementById('installButton');
        const installSection = document.getElementById('installSection');
        const isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;

        function hideInstallUi() {
            if (installButton) installButton.hidden = true;
            if (installSection) installSection.hidden = true;
        }

        if (isStandalone) {
            hideInstallUi();
        }

        if ('serviceWorker' in navigator) {
            navigator.serviceWorker
                .register('/balikos-portal-sw.js', { updateViaCache: 'none' })
                .then((registration) => registration.update())
                .catch(() => {});
        }

        window.addEventListener('beforeinstallprompt', (event) => {
            event.preventDefault();
            deferredInstallPrompt = event;
        });

        installButton?.addEventListener('click', async () => {
            if (!deferredInstallPrompt) {
                alert('Jika tombol install otomatis belum muncul, buka menu browser lalu pilih Install app atau Tambahkan ke layar utama.');
                return;
            }
            deferredInstallPrompt.prompt();
            const choice = await deferredInstallPrompt.userChoice;
            deferredInstallPrompt = null;
            if (choice.outcome === 'accepted') {
                hideInstallUi();
            }
        });

        window.addEventListener('appinstalled', hideInstallUi);

        document.getElementById('notifyButton').addEventListener('click', async () => {
            if (!('Notification' in window)) {
                alert('Browser ini belum mendukung notifikasi.');
                return;
            }
            const permission = await Notification.requestPermission();
            if (permission !== 'granted') {
                alert('Notifikasi belum diizinkan.');
                return;
            }
            const message = nextDueBill
                ? `Tagihan ${nextDueBill.month} ${nextDueBill.year} sebesar ${nextDueBill.amount} jatuh tempo ${nextDueBill.due}.`
                : 'Belum ada tagihan aktif saat ini.';
            new Notification(portalName, { body: message, tag: 'balikos-reminder' });
        });

        const billCards = Array.from(document.querySelectorAll('.bill-card'));
        const loadMoreBills = document.getElementById('loadMoreBills');
        let visibleBills = 5;

        function renderBills() {
            billCards.forEach((card, index) => {
                card.hidden = index >= visibleBills;
            });
            if (loadMoreBills) {
                loadMoreBills.hidden = visibleBills >= billCards.length;
            }
        }

        function showNextBills() {
            visibleBills += 5;
            renderBills();
        }

        loadMoreBills?.addEventListener('click', showNextBills);
        window.addEventListener('scroll', () => {
            if (!loadMoreBills || loadMoreBills.hidden) return;
            const nearBottom = window.innerHeight + window.scrollY >= document.body.offsetHeight - 160;
            if (nearBottom) showNextBills();
        }, { passive: true });
        renderBills();

        async function checkPortalUpdates() {
            try {
                const response = await fetch(statusUrl, { headers: { Accept: 'application/json' } });
                const data = await response.json();
                if (lastPortalStatus && Notification.permission === 'granted') {
                    if (data.announcement_count > lastPortalStatus.announcement_count || data.latest_announcement_update !== lastPortalStatus.latest_announcement_update) {
                        new Notification(portalName, { body: 'Ada info baru dari pemilik kos.', tag: 'balikos-info' });
                    }
                    if (data.tagihan_count > lastPortalStatus.tagihan_count || data.latest_tagihan_update !== lastPortalStatus.latest_tagihan_update) {
                        new Notification(portalName, { body: 'Ada perubahan pada tagihan kos kamu.', tag: 'balikos-tagihan' });
                    }
                }
                lastPortalStatus = data;
            } catch {}
        }

        checkPortalUpdates();
        setInterval(checkPortalUpdates, 60000);
    </script>
</body>
</html>
