<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Barryvdh\DomPDF\Facade\Pdf;

class BalikosApiController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $userId = DB::table('users')->insertGetId([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make($data['password']),
            'role' => 'pemilik_kos',
            'status' => 'aktif',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $plainToken = Str::random(80);
        DB::table('api_tokens')->insert([
            'user_id' => $userId,
            'name' => $data['device_name'] ?? 'android',
            'token_hash' => hash('sha256', $plainToken),
            'last_used_at' => now(),
            'expires_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = DB::table('users')->select('id', 'name', 'email', 'phone', 'role', 'status')->where('id', $userId)->first();

        return response()->json([
            'message' => 'Pendaftaran berhasil.',
            'token_type' => 'Bearer',
            'token' => $plainToken,
            'user' => $user,
        ], 201);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $user = DB::table('users')
            ->select('id', 'name', 'email', 'phone', 'password', 'role', 'status')
            ->where('email', $data['email'])
            ->where('status', 'aktif')
            ->first();

        if (! $user || $user->role !== 'pemilik_kos' || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Email atau password tidak sesuai.'], 422);
        }

        $plainToken = Str::random(80);

        DB::table('api_tokens')->insert([
            'user_id' => $user->id,
            'name' => $data['device_name'] ?? 'android',
            'token_hash' => hash('sha256', $plainToken),
            'last_used_at' => now(),
            'expires_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        unset($user->password);

        return response()->json([
            'token_type' => 'Bearer',
            'token' => $plainToken,
            'user' => $user,
        ]);
    }

    public function googleLogin(Request $request)
    {
        $data = $request->validate([
            'id_token' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $google = Http::timeout(10)->get('https://oauth2.googleapis.com/tokeninfo', [
            'id_token' => $data['id_token'],
        ]);

        if (! $google->ok()) {
            return response()->json(['message' => 'Login Google gagal. Silakan coba lagi.'], 422);
        }

        $profile = $google->json();
        $allowedAudiences = collect([
            config('services.google.android_client_id'),
            config('services.google.web_client_id'),
        ])->filter()->values();

        if ($allowedAudiences->isNotEmpty() && ! $allowedAudiences->contains($profile['aud'] ?? null)) {
            return response()->json(['message' => 'Client Google tidak sesuai dengan aplikasi BALIKOS.'], 422);
        }

        if (($profile['email_verified'] ?? 'false') !== 'true' || empty($profile['email'])) {
            return response()->json(['message' => 'Email Google belum terverifikasi.'], 422);
        }

        $user = DB::table('users')
            ->select('id', 'name', 'email', 'phone', 'role', 'status')
            ->where('email', $profile['email'])
            ->first();

        if ($user && ($user->status !== 'aktif' || $user->role !== 'pemilik_kos')) {
            return response()->json(['message' => 'Akun Google ini tidak aktif sebagai pemilik kos.'], 422);
        }

        if (! $user) {
            $userId = DB::table('users')->insertGetId([
                'name' => $profile['name'] ?? explode('@', $profile['email'])[0],
                'email' => $profile['email'],
                'phone' => null,
                'password' => Hash::make(Str::random(32)),
                'role' => 'pemilik_kos',
                'status' => 'aktif',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $user = DB::table('users')
                ->select('id', 'name', 'email', 'phone', 'role', 'status')
                ->where('id', $userId)
                ->first();
        }

        $plainToken = Str::random(80);
        DB::table('api_tokens')->insert([
            'user_id' => $user->id,
            'name' => $data['device_name'] ?? 'google-mobile',
            'token_hash' => hash('sha256', $plainToken),
            'last_used_at' => now(),
            'expires_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'token_type' => 'Bearer',
            'token' => $plainToken,
            'user' => $user,
        ]);
    }

    public function logout(Request $request)
    {
        DB::table('api_tokens')->where('id', $request->attributes->get('api_token_id'))->delete();

        return response()->json(['message' => 'Logout berhasil.']);
    }

    public function me(Request $request)
    {
        return response()->json(['user' => $request->attributes->get('auth_user')]);
    }

    public function pushTokenStore(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:255'],
            'provider' => ['nullable', Rule::in(['expo'])],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        DB::table('push_notification_tokens')->updateOrInsert(
            ['token' => $data['token']],
            [
                'user_id' => $this->user($request)->id,
                'provider' => $data['provider'] ?? 'expo',
                'device_name' => $data['device_name'] ?? null,
                'last_seen_at' => now(),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return response()->json(['message' => 'Token notifikasi tersimpan.']);
    }

    public function dashboard(Request $request)
    {
        $kosIds = $this->ownedKosIds($request);
        if ($request->filled('kos_id')) {
            $this->assertOwnedKosId($request, (int) $request->input('kos_id'));
            $kosIds = collect([(int) $request->input('kos_id')]);
        }

        $totalKamar = DB::table('kamars')->whereIn('kos_id', $kosIds)->count();
        $kamarTerisi = DB::table('kamars')->whereIn('kos_id', $kosIds)->where('status', 'terisi')->count();

        return response()->json([
            'data' => [
                'total_kos' => $kosIds->count(),
                'total_kamar' => $totalKamar,
                'kamar_terisi' => $kamarTerisi,
                'kamar_kosong' => DB::table('kamars')->whereIn('kos_id', $kosIds)->where('status', 'kosong')->count(),
                'kamar_maintenance' => DB::table('kamars')->whereIn('kos_id', $kosIds)->where('status', 'maintenance')->count(),
                'penghuni_aktif' => DB::table('penghunis')->whereIn('kos_id', $kosIds)->where('status', 'aktif')->count(),
                'tagihan_belum_lunas' => DB::table('tagihans')->whereIn('kos_id', $kosIds)->whereIn('status', ['belum_lunas', 'terlambat'])->sum('nominal'),
                'tagihan_menunggu_verifikasi' => DB::table('tagihans')->whereIn('kos_id', $kosIds)->where('status', 'menunggu_verifikasi')->count(),
                'okupansi' => $totalKamar > 0 ? round(($kamarTerisi / $totalKamar) * 100, 1) : 0,
            ],
        ]);
    }

    public function kosIndex(Request $request)
    {
        $user = $this->user($request);

        $rows = DB::table('kos')
            ->where('owner_id', $user->id)
            ->orderBy('nama_kos')
            ->get();

        return response()->json(['data' => $rows]);
    }

    public function kosStore(Request $request)
    {
        $data = $request->validate($this->kosRules());
        $data['owner_id'] = $this->user($request)->id;
        $data['created_at'] = now();
        $data['updated_at'] = now();

        $id = DB::table('kos')->insertGetId($data);

        return response()->json(['message' => 'Kos berhasil dibuat.', 'data' => $this->ownedKos($request, $id)], 201);
    }

    public function kosShow(Request $request, int $id)
    {
        return response()->json(['data' => $this->ownedKos($request, $id)]);
    }

    public function kosUpdate(Request $request, int $id)
    {
        $this->ownedKos($request, $id);
        $data = $request->validate($this->kosRules(false));
        $data['updated_at'] = now();

        DB::table('kos')->where('id', $id)->where('owner_id', $this->user($request)->id)->update($data);

        return response()->json(['message' => 'Kos berhasil diperbarui.', 'data' => $this->ownedKos($request, $id)]);
    }

    public function kosDelete(Request $request, int $id)
    {
        $this->ownedKos($request, $id);
        DB::table('kos')->where('id', $id)->where('owner_id', $this->user($request)->id)->delete();

        return response()->json(['message' => 'Kos berhasil dihapus.']);
    }

    public function kamarIndex(Request $request)
    {
        $query = DB::table('kamars')->whereIn('kos_id', $this->ownedKosIds($request));
        $this->applyKosFilter($request, $query);

        $rows = $query->orderBy('kos_id')->orderBy('nomor_kamar')->get();

        return response()->json(['data' => $this->roomsWithPhotos($rows)]);
    }

    public function kamarStore(Request $request)
    {
        $data = $request->validate($this->kamarRules());
        $this->assertOwnedKosId($request, (int) $data['kos_id']);
        $data = $this->booleanize($data, $this->facilityFields());
        unset($data['foto'], $data['fotos'], $data['hapus_foto_ids']);
        $data['created_at'] = now();
        $data['updated_at'] = now();

        $id = DB::transaction(function () use ($request, $data) {
            $id = DB::table('kamars')->insertGetId($data);
            $this->storeRoomPhotos($request, $id);
            $this->syncPrimaryRoomPhoto($id);

            return $id;
        });

        return response()->json(['message' => 'Kamar berhasil dibuat.', 'data' => $this->roomWithPhotos($this->ownedRow($request, 'kamars', $id))], 201);
    }

    public function kamarShow(Request $request, int $id)
    {
        $kamar = $this->ownedRow($request, 'kamars', $id);
        $penghuni = DB::table('penghunis')
            ->where('kamar_id', $kamar->id)
            ->where('kos_id', $kamar->kos_id)
            ->where('status', 'aktif')
            ->select('id', 'nama_lengkap', 'no_wa', 'pekerjaan', 'tanggal_masuk', 'status')
            ->first();

        $kamar = $this->roomWithPhotos($kamar);
        $kamar->penghuni_aktif = $penghuni;

        return response()->json(['data' => $kamar]);
    }

    public function kamarUpdate(Request $request, int $id)
    {
        $row = $this->ownedRow($request, 'kamars', $id);
        $data = $request->validate($this->kamarRules(false));
        $data['kos_id'] = $data['kos_id'] ?? $row->kos_id;
        $this->assertOwnedKosId($request, (int) $data['kos_id']);
        $data = $this->booleanize($data, $this->facilityFields());

        $deletedPhotoIds = collect($data['hapus_foto_ids'] ?? [])->map(fn ($value) => (int) $value)->filter()->values();
        unset($data['foto'], $data['fotos'], $data['hapus_foto_ids']);

        if ($deletedPhotoIds->isNotEmpty()) {
            $deleted = DB::table('kamar_fotos')
                ->where('kamar_id', $id)
                ->whereIn('id', $deletedPhotoIds)
                ->get();
            foreach ($deleted as $photo) {
                Storage::disk('public')->delete($photo->path);
            }
            DB::table('kamar_fotos')->whereIn('id', $deleted->pluck('id'))->delete();
        }

        $data['updated_at'] = now();
        DB::table('kamars')->where('id', $id)->update($data);
        $this->storeRoomPhotos($request, $id);
        $this->syncPrimaryRoomPhoto($id);

        return response()->json(['message' => 'Kamar berhasil diperbarui.', 'data' => $this->roomWithPhotos($this->ownedRow($request, 'kamars', $id))]);
    }

    public function kamarDelete(Request $request, int $id)
    {
        $this->ownedRow($request, 'kamars', $id);
        DB::table('kamars')->where('id', $id)->delete();

        return response()->json(['message' => 'Kamar berhasil dihapus.']);
    }

    public function penghuniIndex(Request $request)
    {
        $query = DB::table('penghunis')->whereIn('kos_id', $this->ownedKosIds($request));
        $this->applyKosFilter($request, $query);
        $rows = $query->orderByDesc('id')->get();
        $billSummary = DB::table('tagihans')
            ->select(
                'penghuni_id',
                DB::raw("SUM(CASE WHEN status IN ('belum_lunas', 'terlambat', 'ditolak') THEN 1 ELSE 0 END) as tagihan_aktif_count"),
                DB::raw("SUM(CASE WHEN status IN ('belum_lunas', 'terlambat', 'ditolak') THEN nominal ELSE 0 END) as tagihan_aktif_nominal"),
                DB::raw("SUM(CASE WHEN status = 'menunggu_verifikasi' THEN 1 ELSE 0 END) as tagihan_verifikasi_count")
            )
            ->whereIn('penghuni_id', $rows->pluck('id'))
            ->groupBy('penghuni_id')
            ->get()
            ->keyBy('penghuni_id');

        $rows = $rows->map(function ($row) use ($billSummary) {
            $summary = $billSummary->get($row->id);
            $row->tagihan_aktif_count = (int) ($summary->tagihan_aktif_count ?? 0);
            $row->tagihan_aktif_nominal = (int) ($summary->tagihan_aktif_nominal ?? 0);
            $row->tagihan_verifikasi_count = (int) ($summary->tagihan_verifikasi_count ?? 0);
            $nextDue = $this->nextDueInfo($row);
            $row->jatuh_tempo_berikutnya = $nextDue['date'];
            $row->jatuh_tempo_bulan = $nextDue['month'];
            $row->jatuh_tempo_tahun = $nextDue['year'];
            $row->tagihan_jatuh_tempo_sudah_ada = DB::table('tagihans')
                ->where('penghuni_id', $row->id)
                ->where('bulan', $nextDue['month'])
                ->where('tahun', $nextDue['year'])
                ->exists();
            $row->akan_jatuh_tempo = $row->status === 'aktif'
                && ! $row->tagihan_jatuh_tempo_sudah_ada
                && now()->startOfDay()->diffInDays($nextDue['carbon'], false) >= 0
                && now()->startOfDay()->diffInDays($nextDue['carbon'], false) <= 7;

            return $row;
        });

        return response()->json(['data' => $rows]);
    }

    public function penghuniStore(Request $request)
    {
        $data = $request->validate($this->penghuniRules());
        $this->assertOwnedKosId($request, (int) $data['kos_id']);
        $this->assertOwnedKamarId($request, (int) $data['kamar_id'], (int) $data['kos_id']);
        if ($data['status'] === 'aktif') {
            $this->assertRoomCanReceiveActivePenghuni((int) $data['kamar_id']);
        }
        $data['foto_ktp'] = $this->storeUpload($request, 'foto_ktp', 'balikos/ktp');
        $data['portal_token'] = Str::random(48);
        $data['active_kamar_id'] = $data['status'] === 'aktif' ? $data['kamar_id'] : null;
        $data['created_at'] = now();
        $data['updated_at'] = now();

        $id = DB::table('penghunis')->insertGetId($data);
        if ($data['status'] === 'aktif') {
            DB::table('kamars')->where('id', $data['kamar_id'])->update(['status' => 'terisi', 'updated_at' => now()]);
        }

        return response()->json(['message' => 'Penghuni berhasil dibuat.', 'data' => $this->ownedRow($request, 'penghunis', $id)], 201);
    }

    public function penghuniShow(Request $request, int $id)
    {
        return response()->json(['data' => $this->ownedRow($request, 'penghunis', $id)]);
    }

    public function penghuniUpdate(Request $request, int $id)
    {
        $row = $this->ownedRow($request, 'penghunis', $id);
        $data = $request->validate($this->penghuniRules(false));
        $data['kos_id'] = $data['kos_id'] ?? $row->kos_id;
        $data['kamar_id'] = $data['kamar_id'] ?? $row->kamar_id;
        $data['status'] = $data['status'] ?? $row->status;
        $this->assertOwnedKosId($request, (int) $data['kos_id']);
        $this->assertOwnedKamarId($request, (int) $data['kamar_id'], (int) $data['kos_id']);
        if ($data['status'] === 'aktif') {
            $this->assertRoomCanReceiveActivePenghuni((int) $data['kamar_id'], $id);
        }
        if ($request->hasFile('foto_ktp')) {
            $data['foto_ktp'] = $this->storeUpload($request, 'foto_ktp', 'balikos/ktp');
        }
        $data['active_kamar_id'] = $data['status'] === 'aktif' ? $data['kamar_id'] : null;
        $data['updated_at'] = now();

        DB::table('penghunis')->where('id', $id)->update($data);
        $this->syncRoomStatus((int) $row->kamar_id);
        $this->syncRoomStatus((int) $data['kamar_id']);

        return response()->json(['message' => 'Penghuni berhasil diperbarui.', 'data' => $this->ownedRow($request, 'penghunis', $id)]);
    }

    public function penghuniDelete(Request $request, int $id)
    {
        $row = $this->ownedRow($request, 'penghunis', $id);
        DB::table('penghunis')->where('id', $id)->delete();
        $this->syncRoomStatus((int) $row->kamar_id);

        return response()->json(['message' => 'Penghuni berhasil dihapus.']);
    }

    public function portalLink(Request $request, int $id)
    {
        $penghuni = $this->ownedRow($request, 'penghunis', $id);

        return response()->json([
            'data' => [
                'portal_token' => $penghuni->portal_token,
                'portal_link' => url('/balikos/portal/'.$penghuni->portal_token),
            ],
        ]);
    }

    public function portalUploadBukti(Request $request, string $portalToken, int $id)
    {
        $data = $request->validate([
            'bukti_pembayaran' => ['required', 'image', 'max:4096'],
            'metode_pembayaran' => ['nullable', 'string', 'max:255'],
            'tanggal_bayar' => ['nullable', 'date'],
        ]);

        $penghuni = DB::table('penghunis')->where('portal_token', $portalToken)->first();
        abort_if(! $penghuni, 404, 'Portal penghuni tidak ditemukan.');

        $tagihan = DB::table('tagihans')->where('id', $id)->where('penghuni_id', $penghuni->id)->first();
        abort_if(! $tagihan, 404, 'Tagihan tidak ditemukan.');

        DB::table('tagihans')->where('id', $id)->update([
            'status' => 'menunggu_verifikasi',
            'bukti_pembayaran' => $this->storeUpload($request, 'bukti_pembayaran', 'balikos/bukti-pembayaran'),
            'metode_pembayaran' => $data['metode_pembayaran'] ?? 'transfer',
            'tanggal_bayar' => $data['tanggal_bayar'] ?? now()->toDateString(),
            'tanggal_konfirmasi' => now(),
            'alasan_penolakan' => null,
            'updated_at' => now(),
        ]);

        $kos = DB::table('kos')->where('id', $penghuni->kos_id)->first();
        if ($kos) {
            $this->sendOwnerPush(
                (int) $kos->owner_id,
                'Bukti pembayaran baru',
                $penghuni->nama_lengkap.' mengirim bukti pembayaran. Silakan verifikasi di menu Tagihan.',
                ['type' => 'payment_proof', 'tagihan_id' => $id]
            );
        }

        return response()->json(['message' => 'Bukti pembayaran berhasil dikirim dan menunggu verifikasi.', 'data' => $this->billWithUrls(DB::table('tagihans')->where('id', $id)->first())]);
    }

    public function portalShow(string $portalToken)
    {
        $penghuni = DB::table('penghunis')->where('portal_token', $portalToken)->first();
        abort_if(! $penghuni, 404, 'Portal penghuni tidak ditemukan.');

        $kos = DB::table('kos')->where('id', $penghuni->kos_id)->first();
        $kamar = DB::table('kamars')->where('id', $penghuni->kamar_id)->first();
        $tagihan = DB::table('tagihans')
            ->where('penghuni_id', $penghuni->id)
            ->whereIn('status', ['belum_lunas', 'menunggu_verifikasi', 'terlambat', 'ditolak'])
            ->orderBy('tahun')
            ->orderBy('bulan')
            ->get()
            ->map(fn ($row) => $this->billWithUrls($row));
        $paymentMethods = DB::table('payment_methods')
            ->where('kos_id', $penghuni->kos_id)
            ->where('is_active', true)
            ->orderByRaw("CASE WHEN jenis = 'qris' THEN 0 ELSE 1 END")
            ->get();

        return response()->json([
            'data' => [
                'kos' => $kos,
                'kamar' => $kamar,
                'penghuni' => $penghuni,
                'tagihan' => $tagihan,
                'payment_methods' => $paymentMethods,
            ],
        ]);
    }

    public function tagihanIndex(Request $request)
    {
        $query = DB::table('tagihans')->whereIn('kos_id', $this->ownedKosIds($request));
        $this->applyKosFilter($request, $query);

        foreach (['bulan', 'tahun', 'status'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->input($field));
            }
        }

        return response()->json(['data' => $this->billsWithUrls($query->orderByDesc('tahun')->orderByDesc('bulan')->get())]);
    }

    public function tagihanGenerate(Request $request)
    {
        $data = $request->validate([
            'kos_id' => ['required', 'integer'],
            'kamar_id' => ['nullable', 'integer'],
            'bulan' => ['required', 'integer', 'between:1,12'],
            'tahun' => ['required', 'integer', 'between:2020,2100'],
            'tanggal_jatuh_tempo' => ['nullable', 'date'],
            'jumlah_bulan' => ['nullable', 'integer', 'between:1,24'],
        ]);
        $this->assertOwnedKosId($request, (int) $data['kos_id']);
        if (! empty($data['kamar_id'])) {
            $this->assertOwnedKamarId($request, (int) $data['kamar_id'], (int) $data['kos_id']);
        }

        $query = DB::table('penghunis')
            ->join('kamars', 'kamars.id', '=', 'penghunis.kamar_id')
            ->where('penghunis.kos_id', $data['kos_id'])
            ->where('penghunis.status', 'aktif')
            ->select('penghunis.id as penghuni_id', 'penghunis.kos_id', 'penghunis.kamar_id', 'penghunis.jatuh_tempo_hari', 'penghunis.tanggal_masuk', 'kamars.harga_bulanan');

        if (! empty($data['kamar_id'])) {
            $query->where('penghunis.kamar_id', $data['kamar_id']);
        }

        $penghunis = $query->get();
        $total = 0;
        $period = now()->setDate((int) $data['tahun'], (int) $data['bulan'], 1);
        for ($i = 0; $i < (int) ($data['jumlah_bulan'] ?? 1); $i++) {
            $target = $period->copy()->addMonths($i);
            $dueDate = null;
            if (! empty($data['tanggal_jatuh_tempo'])) {
                $dueDay = min((int) date('d', strtotime($data['tanggal_jatuh_tempo'])), $target->daysInMonth);
                $dueDate = $target->copy()->setDate((int) $target->year, (int) $target->month, $dueDay)->toDateString();
            }
            $total += $this->createBills($penghunis, (int) $target->month, (int) $target->year, $dueDate);
        }

        return response()->json(['message' => 'Tagihan berhasil digenerate.', 'total' => $total]);
    }

    public function tagihanBayarMulti(Request $request)
    {
        $data = $request->validate([
            'kos_id' => ['required', 'integer'],
            'kamar_id' => ['nullable', 'integer'],
            'penghuni_id' => ['nullable', 'integer'],
            'bulan' => ['required', 'integer', 'between:1,12'],
            'tahun' => ['required', 'integer', 'between:2020,2100'],
            'jumlah_bulan' => ['required', 'integer', 'between:1,24'],
            'tanggal_bayar' => ['nullable', 'date'],
            'metode_pembayaran' => ['nullable', 'string', 'max:255'],
        ]);
        $this->assertOwnedKosId($request, (int) $data['kos_id']);
        if (! empty($data['kamar_id'])) {
            $this->assertOwnedKamarId($request, (int) $data['kamar_id'], (int) $data['kos_id']);
        }

        $query = DB::table('penghunis')
            ->join('kamars', 'kamars.id', '=', 'penghunis.kamar_id')
            ->where('penghunis.kos_id', $data['kos_id'])
            ->where('penghunis.status', 'aktif')
            ->select('penghunis.id as penghuni_id', 'penghunis.kos_id', 'penghunis.kamar_id', 'penghunis.jatuh_tempo_hari', 'penghunis.tanggal_masuk', 'kamars.harga_bulanan');

        if (! empty($data['penghuni_id'])) {
            $query->where('penghunis.id', $data['penghuni_id']);
        }
        if (! empty($data['kamar_id'])) {
            $query->where('penghunis.kamar_id', $data['kamar_id']);
        }

        $penghunis = $query->get();
        abort_if($penghunis->isEmpty(), 422, 'Penghuni aktif tidak ditemukan untuk pembayaran ini.');

        $period = now()->setDate((int) $data['tahun'], (int) $data['bulan'], 1);
        $billIds = [];
        for ($i = 0; $i < (int) $data['jumlah_bulan']; $i++) {
            $target = $period->copy()->addMonths($i);
            $this->createBills($penghunis, (int) $target->month, (int) $target->year, null);
            $ids = DB::table('tagihans')
                ->whereIn('penghuni_id', $penghunis->pluck('penghuni_id'))
                ->where('bulan', (int) $target->month)
                ->where('tahun', (int) $target->year)
                ->pluck('id')
                ->all();
            $billIds = array_merge($billIds, $ids);
        }

        DB::table('tagihans')->whereIn('id', $billIds)->update([
            'status' => 'lunas',
            'tanggal_bayar' => $data['tanggal_bayar'] ?? now()->toDateString(),
            'metode_pembayaran' => $data['metode_pembayaran'] ?? 'tunai',
            'tanggal_verifikasi' => now(),
            'diverifikasi_oleh' => $this->user($request)->id,
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Pembayaran multi-bulan berhasil dicatat.', 'total' => count(array_unique($billIds))]);
    }

    public function tagihanAutoGenerate(Request $request)
    {
        $data = $request->validate([
            'kos_id' => ['required', 'integer'],
            'days_before_due' => ['nullable', 'integer', 'between:0,30'],
        ]);
        $this->assertOwnedKosId($request, (int) $data['kos_id']);

        $total = $this->autoGenerateForKos((int) $data['kos_id'], (int) ($data['days_before_due'] ?? 7));

        return response()->json(['message' => 'Auto-generate tagihan selesai.', 'total' => $total]);
    }

    public function tagihanShow(Request $request, int $id)
    {
        return response()->json(['data' => $this->billWithUrls($this->ownedRow($request, 'tagihans', $id))]);
    }

    public function tagihanLunas(Request $request, int $id)
    {
        $this->ownedRow($request, 'tagihans', $id);

        DB::transaction(function () use ($request, $id) {
            $bill = $this->lockedOwnedBill($request, $id);
            if ($bill->status === 'lunas') {
                return;
            }

            $method = $request->input('metode_pembayaran', 'tunai');
            $fee = $method === 'qris' ? $this->qrisFee((int) $bill->nominal) : 0;
            DB::table('tagihans')->where('id', $id)->update([
                'status' => 'lunas',
                'tanggal_bayar' => $request->input('tanggal_bayar', now()->toDateString()),
                'metode_pembayaran' => $method,
                'biaya_platform' => $fee,
                'total_dibayar' => (int) $bill->nominal + $fee,
                'tanggal_verifikasi' => now(),
                'diverifikasi_oleh' => $this->user($request)->id,
                'updated_at' => now(),
            ]);
            if ($method === 'qris') {
                $this->creditQrisPayment($bill);
            }
        });

        return response()->json(['message' => 'Tagihan ditandai lunas.', 'data' => $this->billWithUrls($this->ownedRow($request, 'tagihans', $id))]);
    }

    public function tagihanVerifikasi(Request $request, int $id)
    {
        $this->ownedRow($request, 'tagihans', $id);

        DB::transaction(function () use ($request, $id) {
            $bill = $this->lockedOwnedBill($request, $id);
            if ($bill->status === 'lunas') {
                return;
            }

            $method = $bill->metode_pembayaran ?: ($this->activeQrisMethod((int) $bill->kos_id) ? 'qris' : 'transfer');
            $fee = $method === 'qris' ? $this->qrisFee((int) $bill->nominal) : 0;
            DB::table('tagihans')->where('id', $id)->update([
                'status' => 'lunas',
                'tanggal_verifikasi' => now(),
                'tanggal_bayar' => now()->toDateString(),
                'metode_pembayaran' => $method,
                'biaya_platform' => $fee,
                'total_dibayar' => (int) $bill->nominal + $fee,
                'diverifikasi_oleh' => $this->user($request)->id,
                'updated_at' => now(),
            ]);
            if ($method === 'qris') {
                $this->creditQrisPayment($bill);
            }
        });

        return response()->json(['message' => 'Pembayaran berhasil diverifikasi.', 'data' => $this->billWithUrls($this->ownedRow($request, 'tagihans', $id))]);
    }

    public function tagihanTolak(Request $request, int $id)
    {
        $data = $request->validate(['alasan_penolakan' => ['required', 'string', 'max:1000']]);
        $this->ownedRow($request, 'tagihans', $id);
        DB::table('tagihans')->where('id', $id)->update([
            'status' => 'ditolak',
            'alasan_penolakan' => $data['alasan_penolakan'],
            'tanggal_verifikasi' => now(),
            'diverifikasi_oleh' => $this->user($request)->id,
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Pembayaran ditolak.', 'data' => $this->billWithUrls($this->ownedRow($request, 'tagihans', $id))]);
    }

    public function paymentMethodIndex(Request $request)
    {
        $query = DB::table('payment_methods')->whereIn('kos_id', $this->ownedKosIds($request));
        $this->applyKosFilter($request, $query);
        $wallet = null;
        if ($request->filled('kos_id')) {
            $kosId = (int) $request->input('kos_id');
            $this->assertOwnedKosId($request, $kosId);
            $wallet = $this->walletForKos($kosId);
        }

        return response()->json(['data' => $query->orderByDesc('is_active')->get(), 'wallet' => $wallet]);
    }

    public function paymentMethodStore(Request $request)
    {
        $data = $request->validate($this->paymentMethodRules());
        $this->assertOwnedKosId($request, (int) $data['kos_id']);
        $data = $this->normalizePaymentMethod($data, $request);
        $data['is_active'] = $request->boolean('is_active', true);
        $data['qris_image'] = $this->storeUpload($request, 'qris_image', 'balikos/qris');
        $data['created_at'] = now();
        $data['updated_at'] = now();

        if ($data['is_active']) {
            DB::table('payment_methods')->where('kos_id', $data['kos_id'])->update(['is_active' => false, 'updated_at' => now()]);
        }
        $this->walletForKos((int) $data['kos_id']);
        $id = DB::table('payment_methods')->insertGetId($data);

        return response()->json(['message' => 'Metode pembayaran berhasil dibuat.', 'data' => $this->ownedRow($request, 'payment_methods', $id)], 201);
    }

    public function paymentMethodUpdate(Request $request, int $id)
    {
        $row = $this->ownedRow($request, 'payment_methods', $id);
        $data = $request->validate($this->paymentMethodRules(false));
        $data['kos_id'] = $data['kos_id'] ?? $row->kos_id;
        $this->assertOwnedKosId($request, (int) $data['kos_id']);
        $data = $this->normalizePaymentMethod($data, $request, $row);
        if ($request->has('is_active')) {
            $data['is_active'] = $request->boolean('is_active');
        }
        if ($request->hasFile('qris_image')) {
            $data['qris_image'] = $this->storeUpload($request, 'qris_image', 'balikos/qris');
        }
        $data['updated_at'] = now();
        if (($data['is_active'] ?? false) === true) {
            DB::table('payment_methods')->where('kos_id', $data['kos_id'])->where('id', '!=', $id)->update(['is_active' => false, 'updated_at' => now()]);
        }
        $this->walletForKos((int) $data['kos_id']);
        DB::table('payment_methods')->where('id', $id)->update($data);

        return response()->json(['message' => 'Metode pembayaran berhasil diperbarui.', 'data' => $this->ownedRow($request, 'payment_methods', $id)]);
    }

    public function paymentMethodDelete(Request $request, int $id)
    {
        $this->ownedRow($request, 'payment_methods', $id);
        DB::table('payment_methods')->where('id', $id)->delete();

        return response()->json(['message' => 'Metode pembayaran berhasil dihapus.']);
    }

    public function walletWithdraw(Request $request)
    {
        $data = $request->validate([
            'kos_id' => ['required', 'integer'],
            'nominal' => ['required', 'integer', 'min:10000'],
            'nama_bank' => ['required', 'string', 'max:100'],
            'nomor_rekening' => ['required', 'string', 'max:100'],
            'atas_nama' => ['required', 'string', 'max:150'],
        ]);
        $this->assertOwnedKosId($request, (int) $data['kos_id']);

        $wallet = $this->walletForKos((int) $data['kos_id']);
        abort_if((int) $wallet->saldo_tersedia < (int) $data['nominal'], 422, 'Saldo tersedia tidak cukup untuk penarikan ini.');

        DB::transaction(function () use ($data, $wallet) {
            DB::table('kos_wallets')->where('id', $wallet->id)->update([
                'saldo_tersedia' => DB::raw('saldo_tersedia - '.(int) $data['nominal']),
                'total_ditarik' => DB::raw('total_ditarik + '.(int) $data['nominal']),
                'updated_at' => now(),
            ]);

            DB::table('kos_wallet_withdrawals')->insert([
                'kos_id' => $data['kos_id'],
                'wallet_id' => $wallet->id,
                'nominal' => $data['nominal'],
                'nama_bank' => $data['nama_bank'],
                'nomor_rekening' => $data['nomor_rekening'],
                'atas_nama' => $data['atas_nama'],
                'status' => 'menunggu',
                'catatan' => 'Pengajuan penarikan saldo QRIS.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return response()->json([
            'message' => 'Pengajuan tarik saldo berhasil dibuat.',
            'wallet' => $this->walletForKos((int) $data['kos_id']),
        ]);
    }

    public function keuanganIndex(Request $request)
    {
        $report = $this->financialReportData($request);

        return response()->json([
            'data' => $report['transactions'],
            'summary' => $report['summary'],
        ]);
    }

    public function keuanganPdf(Request $request)
    {
        $report = $this->financialReportData($request);
        $kosName = $report['kos']?->nama_kos ?? 'Semua Kos';
        $filename = 'laporan-keuangan-'.Str::slug($kosName).'-'.$report['summary']['tahun'].'-'.str_pad((string) $report['summary']['bulan'], 2, '0', STR_PAD_LEFT).'.pdf';
        $pdf = Pdf::loadView('pdf.balikos-keuangan', $report)->setPaper('a4', 'portrait');

        return $pdf->download($filename);
    }

    public function keuanganStore(Request $request)
    {
        $data = $request->validate($this->keuanganRules());
        $this->assertOwnedKosId($request, (int) $data['kos_id']);
        $this->assertOwnedKategori($request, $data['kategori_id'] ?? null, (int) $data['kos_id']);
        $data['created_by'] = $this->user($request)->id;
        $data['created_at'] = now();
        $data['updated_at'] = now();
        $id = DB::table('transaksi_keuangan')->insertGetId($data);

        return response()->json(['message' => 'Transaksi keuangan berhasil dibuat.', 'data' => $this->ownedRow($request, 'transaksi_keuangan', $id)], 201);
    }

    public function keuanganUpdate(Request $request, int $id)
    {
        $row = $this->ownedRow($request, 'transaksi_keuangan', $id);
        $data = $request->validate($this->keuanganRules(false));
        $data['kos_id'] = $data['kos_id'] ?? $row->kos_id;
        $this->assertOwnedKosId($request, (int) $data['kos_id']);
        $this->assertOwnedKategori($request, $data['kategori_id'] ?? $row->kategori_id, (int) $data['kos_id']);
        $data['updated_at'] = now();
        DB::table('transaksi_keuangan')->where('id', $id)->update($data);

        return response()->json(['message' => 'Transaksi keuangan berhasil diperbarui.', 'data' => $this->ownedRow($request, 'transaksi_keuangan', $id)]);
    }

    public function keuanganDelete(Request $request, int $id)
    {
        $this->ownedRow($request, 'transaksi_keuangan', $id);
        DB::table('transaksi_keuangan')->where('id', $id)->delete();

        return response()->json(['message' => 'Transaksi keuangan berhasil dihapus.']);
    }

    public function pengumumanIndex(Request $request)
    {
        $query = DB::table('pengumuman_kos')->whereIn('kos_id', $this->ownedKosIds($request));
        $this->applyKosFilter($request, $query);

        return response()->json(['data' => $query->orderByDesc('id')->get()]);
    }

    public function pengumumanStore(Request $request)
    {
        $data = $request->validate($this->pengumumanRules());
        $this->assertOwnedKosId($request, (int) $data['kos_id']);
        $data['created_at'] = now();
        $data['updated_at'] = now();
        $id = DB::table('pengumuman_kos')->insertGetId($data);

        return response()->json(['message' => 'Pengumuman berhasil dibuat.', 'data' => $this->ownedRow($request, 'pengumuman_kos', $id)], 201);
    }

    public function pengumumanUpdate(Request $request, int $id)
    {
        $row = $this->ownedRow($request, 'pengumuman_kos', $id);
        $data = $request->validate($this->pengumumanRules(false));
        $data['kos_id'] = $data['kos_id'] ?? $row->kos_id;
        $this->assertOwnedKosId($request, (int) $data['kos_id']);
        $data['updated_at'] = now();
        DB::table('pengumuman_kos')->where('id', $id)->update($data);

        return response()->json(['message' => 'Pengumuman berhasil diperbarui.', 'data' => $this->ownedRow($request, 'pengumuman_kos', $id)]);
    }

    public function pengumumanDelete(Request $request, int $id)
    {
        $this->ownedRow($request, 'pengumuman_kos', $id);
        DB::table('pengumuman_kos')->where('id', $id)->delete();

        return response()->json(['message' => 'Pengumuman berhasil dihapus.']);
    }

    private function user(Request $request)
    {
        return $request->attributes->get('auth_user');
    }

    private function ownedKosIds(Request $request)
    {
        return DB::table('kos')->where('owner_id', $this->user($request)->id)->pluck('id');
    }

    private function ownedKos(Request $request, int $id)
    {
        $row = DB::table('kos')->where('id', $id)->where('owner_id', $this->user($request)->id)->first();
        abort_if(! $row, 404, 'Data kos tidak ditemukan.');

        return $row;
    }

    private function ownedRow(Request $request, string $table, int $id)
    {
        $row = DB::table($table)->where('id', $id)->whereIn('kos_id', $this->ownedKosIds($request))->first();
        abort_if(! $row, 404, 'Data tidak ditemukan.');

        return $row;
    }

    private function lockedOwnedBill(Request $request, int $id)
    {
        $row = DB::table('tagihans')
            ->where('id', $id)
            ->whereIn('kos_id', $this->ownedKosIds($request))
            ->lockForUpdate()
            ->first();
        abort_if(! $row, 404, 'Data tidak ditemukan.');

        return $row;
    }

    private function assertOwnedKosId(Request $request, int $kosId): void
    {
        $exists = DB::table('kos')->where('id', $kosId)->where('owner_id', $this->user($request)->id)->exists();
        abort_if(! $exists, 403, 'Kos tidak boleh diakses.');
    }

    private function assertOwnedKamarId(Request $request, int $kamarId, int $kosId): void
    {
        $exists = DB::table('kamars')->where('id', $kamarId)->where('kos_id', $kosId)->whereIn('kos_id', $this->ownedKosIds($request))->exists();
        abort_if(! $exists, 403, 'Kamar tidak boleh diakses.');
    }

    private function assertRoomCanReceiveActivePenghuni(int $kamarId, ?int $exceptPenghuniId = null): void
    {
        $query = DB::table('penghunis')->where('kamar_id', $kamarId)->where('status', 'aktif');
        if ($exceptPenghuniId) {
            $query->where('id', '!=', $exceptPenghuniId);
        }

        abort_if($query->exists(), 422, 'Kamar ini sudah memiliki penghuni aktif.');
    }

    private function syncRoomStatus(int $kamarId): void
    {
        $hasActive = DB::table('penghunis')->where('kamar_id', $kamarId)->where('status', 'aktif')->exists();
        $status = $hasActive ? 'terisi' : 'kosong';

        DB::table('kamars')
            ->where('id', $kamarId)
            ->where('status', '!=', 'maintenance')
            ->update(['status' => $status, 'updated_at' => now()]);
    }

    private function assertOwnedKategori(Request $request, ?int $kategoriId, int $kosId): void
    {
        if (! $kategoriId) {
            return;
        }

        $exists = DB::table('kategori_keuangan')->where('id', $kategoriId)->where('kos_id', $kosId)->whereIn('kos_id', $this->ownedKosIds($request))->exists();
        abort_if(! $exists, 403, 'Kategori tidak boleh diakses.');
    }

    private function applyKosFilter(Request $request, $query): void
    {
        if ($request->filled('kos_id')) {
            $this->assertOwnedKosId($request, (int) $request->input('kos_id'));
            $query->where('kos_id', $request->input('kos_id'));
        }
    }

    private function storeUpload(Request $request, string $field, string $directory): ?string
    {
        if (! $request->hasFile($field)) {
            return null;
        }

        return $request->file($field)->store($directory, 'public');
    }

    private function financialReportData(Request $request): array
    {
        $kosIds = $this->ownedKosIds($request);
        $kosId = $request->filled('kos_id') ? (int) $request->input('kos_id') : null;
        $bulan = $request->filled('bulan') ? (int) $request->input('bulan') : (int) now()->month;
        $tahun = $request->filled('tahun') ? (int) $request->input('tahun') : (int) now()->year;
        $kos = null;

        if ($kosId) {
            $this->assertOwnedKosId($request, $kosId);
            $kos = DB::table('kos')->where('id', $kosId)->first();
        }

        $transactionQuery = DB::table('transaksi_keuangan')
            ->whereIn('kos_id', $kosIds)
            ->whereMonth('tanggal', $bulan)
            ->whereYear('tanggal', $tahun);
        $this->applyKosFilter($request, $transactionQuery);

        $incomeQuery = DB::table('transaksi_keuangan')
            ->whereIn('kos_id', $kosIds)
            ->where('jenis', 'pemasukan')
            ->whereMonth('tanggal', $bulan)
            ->whereYear('tanggal', $tahun);
        $expenseQuery = DB::table('transaksi_keuangan')
            ->whereIn('kos_id', $kosIds)
            ->where('jenis', 'pengeluaran')
            ->whereMonth('tanggal', $bulan)
            ->whereYear('tanggal', $tahun);
        $rentQuery = DB::table('tagihans')
            ->leftJoin('kamars', 'kamars.id', '=', 'tagihans.kamar_id')
            ->leftJoin('penghunis', 'penghunis.id', '=', 'tagihans.penghuni_id')
            ->whereIn('tagihans.kos_id', $kosIds)
            ->where('tagihans.status', 'lunas')
            ->where('tagihans.bulan', $bulan)
            ->where('tagihans.tahun', $tahun);

        if ($kosId) {
            $incomeQuery->where('kos_id', $kosId);
            $expenseQuery->where('kos_id', $kosId);
            $rentQuery->where('tagihans.kos_id', $kosId);
        }

        $rentRows = (clone $rentQuery)
            ->orderBy('kamars.nomor_kamar')
            ->get([
                'tagihans.id',
                'tagihans.nominal',
                'tagihans.tanggal_bayar',
                'tagihans.metode_pembayaran',
                'kamars.nomor_kamar',
                'penghunis.nama_lengkap',
            ]);
        $transactions = $transactionQuery->orderByDesc('tanggal')->get();

        $pendapatanSewa = (int) $rentRows->sum('nominal');
        $pemasukanLain = (int) $incomeQuery->sum('nominal');
        $pengeluaran = (int) $expenseQuery->sum('nominal');
        $totalPemasukan = $pendapatanSewa + $pemasukanLain;
        $labaRugi = $totalPemasukan - $pengeluaran;

        return [
            'kos' => $kos,
            'transactions' => $transactions,
            'rentBills' => $rentRows,
            'summary' => [
                'bulan' => $bulan,
                'tahun' => $tahun,
                'pendapatan_sewa' => $pendapatanSewa,
                'pemasukan_lain' => $pemasukanLain,
                'total_pemasukan' => $totalPemasukan,
                'pengeluaran' => $pengeluaran,
                'laba_rugi' => $labaRugi,
                'margin_persen' => $totalPemasukan > 0 ? round(($labaRugi / $totalPemasukan) * 100, 1) : 0,
                'status' => $labaRugi >= 0 ? 'untung' : 'rugi',
            ],
        ];
    }

    private function storeRoomPhotos(Request $request, int $kamarId): void
    {
        $files = [];
        if ($request->hasFile('foto')) {
            $files[] = $request->file('foto');
        }
        if ($request->hasFile('fotos')) {
            foreach ((array) $request->file('fotos') as $file) {
                $files[] = $file;
            }
        }

        if (! $files) {
            return;
        }

        $currentCount = DB::table('kamar_fotos')->where('kamar_id', $kamarId)->count();
        $nextOrder = (int) DB::table('kamar_fotos')->where('kamar_id', $kamarId)->max('urutan');
        foreach (array_slice($files, 0, max(0, 5 - $currentCount)) as $file) {
            DB::table('kamar_fotos')->insert([
                'kamar_id' => $kamarId,
                'path' => $this->storeOptimizedRoomPhoto($file),
                'urutan' => ++$nextOrder,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function storeOptimizedRoomPhoto($file): string
    {
        $fallback = fn () => $file->store('balikos/kamar', 'public');
        if (! function_exists('imagecreatefromstring')) {
            return $fallback();
        }

        $source = @imagecreatefromstring(file_get_contents($file->getRealPath()));
        if (! $source) {
            return $fallback();
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $maxSide = 960;
        $scale = min(1, $maxSide / max($width, $height));
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));
        $target = imagecreatetruecolor($targetWidth, $targetHeight);

        $white = imagecolorallocate($target, 255, 255, 255);
        imagefilledrectangle($target, 0, 0, $targetWidth, $targetHeight, $white);
        imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        ob_start();
        imagejpeg($target, null, 72);
        $contents = ob_get_clean();
        imagedestroy($source);
        imagedestroy($target);

        if (! $contents) {
            return $fallback();
        }

        $path = 'balikos/kamar/'.Str::uuid().'.jpg';
        Storage::disk('public')->put($path, $contents);

        return $path;
    }

    private function syncPrimaryRoomPhoto(int $kamarId): void
    {
        $photo = DB::table('kamar_fotos')
            ->where('kamar_id', $kamarId)
            ->orderBy('urutan')
            ->orderBy('id')
            ->first();

        DB::table('kamars')->where('id', $kamarId)->update([
            'foto' => $photo ? $photo->path : null,
            'updated_at' => now(),
        ]);
    }

    private function roomsWithPhotos($rows)
    {
        $roomIds = $rows->pluck('id');
        $photos = DB::table('kamar_fotos')
            ->whereIn('kamar_id', $roomIds)
            ->orderBy('urutan')
            ->orderBy('id')
            ->get()
            ->groupBy('kamar_id');

        return $rows->map(function ($row) use ($photos) {
            return $this->roomWithPhotos($row, $photos->get($row->id, collect()));
        });
    }

    private function roomWithPhotos($row, $photos = null)
    {
        if (! $row) {
            return $row;
        }

        $photos ??= DB::table('kamar_fotos')
            ->where('kamar_id', $row->id)
            ->orderBy('urutan')
            ->orderBy('id')
            ->get();

        if ($photos->isEmpty() && ! empty($row->foto)) {
            $photos = collect([(object) [
                'id' => null,
                'kamar_id' => $row->id,
                'path' => $row->foto,
                'urutan' => 1,
            ]]);
        }

        $row->fotos = $photos->map(fn ($photo) => [
            'id' => $photo->id,
            'path' => $photo->path,
            'url' => asset('storage/'.$photo->path),
            'urutan' => (int) $photo->urutan,
        ])->values();
        $row->foto_url = $row->foto ? asset('storage/'.$row->foto) : null;

        return $row;
    }

    private function billsWithUrls($rows)
    {
        return $rows->map(fn ($row) => $this->billWithUrls($row));
    }

    private function billWithUrls($row)
    {
        if ($row && ! empty($row->bukti_pembayaran)) {
            $row->bukti_pembayaran_url = asset('storage/'.$row->bukti_pembayaran);
        }
        if ($row) {
            $fee = (int) ($row->biaya_platform ?? 0);
            if ($fee === 0 && $this->activeQrisMethod((int) $row->kos_id) && in_array($row->status, ['belum_lunas', 'terlambat', 'ditolak', 'menunggu_verifikasi'], true)) {
                $fee = $this->qrisFee((int) $row->nominal);
            }
            $row->biaya_platform = $fee;
            $row->total_dibayar = (int) ($row->total_dibayar ?? ((int) $row->nominal + $fee));
        }

        return $row;
    }

    private function booleanize(array $data, array $fields): array
    {
        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = (bool) $data[$field];
            }
        }

        return $data;
    }

    private function kosRules(bool $required = true): array
    {
        $base = $required ? ['required'] : ['sometimes', 'required'];

        return [
            'nama_kos' => [...$base, 'string', 'max:255'],
            'alamat' => [...$base, 'string'],
            'kecamatan' => [...$base, 'string', 'max:255'],
            'desa' => ['nullable', 'string', 'max:255'],
            'banjar' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'no_wa' => ['nullable', 'string', 'max:30'],
            'aturan_kos' => ['nullable', 'string'],
            'catatan' => ['nullable', 'string'],
            'status' => [...$base, Rule::in(['aktif', 'nonaktif'])],
        ];
    }

    private function kamarRules(bool $required = true): array
    {
        $base = $required ? ['required'] : ['sometimes', 'required'];

        return [
            'kos_id' => [...$base, 'integer'],
            'nomor_kamar' => [...$base, 'string', 'max:255'],
            'tipe_kamar' => ['nullable', 'string', 'max:255'],
            'harga_bulanan' => [...$base, 'integer', 'min:0'],
            'status' => [...$base, Rule::in(['kosong', 'terisi', 'maintenance'])],
            'fasilitas_ac' => ['sometimes', 'boolean'],
            'fasilitas_km_dalam' => ['sometimes', 'boolean'],
            'fasilitas_wifi' => ['sometimes', 'boolean'],
            'fasilitas_kasur' => ['sometimes', 'boolean'],
            'fasilitas_lemari' => ['sometimes', 'boolean'],
            'fasilitas_meja' => ['sometimes', 'boolean'],
            'fasilitas_parkir' => ['sometimes', 'boolean'],
            'foto' => ['nullable', 'image', 'max:5120'],
            'fotos' => ['nullable', 'array', 'max:5'],
            'fotos.*' => ['image', 'max:5120'],
            'hapus_foto_ids' => ['nullable', 'array'],
            'hapus_foto_ids.*' => ['integer'],
            'catatan' => ['nullable', 'string'],
        ];
    }

    private function penghuniRules(bool $required = true): array
    {
        $base = $required ? ['required'] : ['sometimes', 'required'];

        return [
            'kos_id' => [...$base, 'integer'],
            'kamar_id' => [...$base, 'integer'],
            'nama_lengkap' => [...$base, 'string', 'max:255'],
            'no_ktp' => ['nullable', 'string', 'max:30'],
            'foto_ktp' => ['nullable', 'image', 'max:4096'],
            'no_wa' => ['nullable', 'string', 'max:30'],
            'alamat_asal' => ['nullable', 'string'],
            'pekerjaan' => ['nullable', 'string', 'max:255'],
            'no_kendaraan' => ['nullable', 'string', 'max:50'],
            'kontak_darurat' => ['nullable', 'string', 'max:255'],
            'tanggal_masuk' => [...$base, 'date'],
            'jatuh_tempo_hari' => ['nullable', 'integer', 'between:1,28'],
            'tanggal_keluar' => ['nullable', 'date'],
            'status' => [...$base, Rule::in(['aktif', 'keluar'])],
        ];
    }

    private function paymentMethodRules(bool $required = true): array
    {
        $base = $required ? ['required'] : ['sometimes', 'required'];

        return [
            'kos_id' => [...$base, 'integer'],
            'jenis' => [...$base, Rule::in(['bank', 'qris', 'tunai'])],
            'verification_mode' => ['nullable', Rule::in(['manual', 'automatic'])],
            'gateway_provider' => ['nullable', 'string', 'max:100'],
            'gateway_account_id' => ['nullable', 'string', 'max:255'],
            'gateway_reference' => ['nullable', 'string', 'max:255'],
            'nama_bank' => ['nullable', 'string', 'max:255'],
            'nomor_rekening' => ['nullable', 'string', 'max:255'],
            'atas_nama' => ['nullable', 'string', 'max:255'],
            'qris_image' => ['nullable', 'image', 'max:2048'],
            'qris_url' => ['nullable', 'string', 'max:2048'],
            'instruksi_pembayaran' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    private function normalizePaymentMethod(array $data, Request $request, ?object $existing = null): array
    {
        $jenis = $data['jenis'] ?? ($existing ? $existing->jenis : null);
        if ($jenis === 'qris') {
            $data['verification_mode'] = 'automatic';
            $data['gateway_provider'] = 'xendit';
            $data['nama_bank'] = 'QRIS Xendit';
            $data['nomor_rekening'] = null;
            $data['atas_nama'] = null;
            $data['instruksi_pembayaran'] = $data['instruksi_pembayaran'] ?? 'Scan QRIS Xendit. Pembayaran akan terverifikasi otomatis setelah gateway mengirim konfirmasi.';
        }

        if ($jenis === 'bank') {
            $data['verification_mode'] = 'manual';
            $data['gateway_provider'] = null;
            $data['gateway_account_id'] = null;
            $data['gateway_reference'] = null;
            $data['qris_url'] = null;
            $data['instruksi_pembayaran'] = $data['instruksi_pembayaran'] ?? 'Transfer sesuai nominal tagihan, lalu unggah bukti pembayaran untuk diverifikasi pemilik kos.';
        }

        return $data;
    }

    private function walletForKos(int $kosId): object
    {
        $wallet = DB::table('kos_wallets')->where('kos_id', $kosId)->first();
        if ($wallet) {
            return $wallet;
        }

        $id = DB::table('kos_wallets')->insertGetId([
            'kos_id' => $kosId,
            'saldo_tersedia' => 0,
            'saldo_pending' => 0,
            'total_ditarik' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('kos_wallets')->where('id', $id)->first();
    }

    private function qrisFee(int $nominal): int
    {
        return (int) ceil($nominal * 0.01);
    }

    private function activeQrisMethod(int $kosId): ?object
    {
        return DB::table('payment_methods')
            ->where('kos_id', $kosId)
            ->where('jenis', 'qris')
            ->where('is_active', true)
            ->first();
    }

    private function creditQrisPayment(object $bill): void
    {
        $wallet = $this->walletForKos((int) $bill->kos_id);
        DB::table('kos_wallets')->where('id', $wallet->id)->update([
            'saldo_tersedia' => DB::raw('saldo_tersedia + '.(int) $bill->nominal),
            'updated_at' => now(),
        ]);
    }

    private function sendOwnerPush(int $ownerId, string $title, string $body, array $data = []): void
    {
        $tokens = DB::table('push_notification_tokens')
            ->where('user_id', $ownerId)
            ->where('provider', 'expo')
            ->pluck('token')
            ->filter(fn ($token) => str_starts_with($token, 'ExponentPushToken[') || str_starts_with($token, 'ExpoPushToken['))
            ->values();

        if ($tokens->isEmpty()) {
            return;
        }

        $messages = $tokens->map(fn ($token) => [
            'to' => $token,
            'sound' => 'default',
            'title' => $title,
            'body' => $body,
            'data' => $data,
        ])->all();

        try {
            Http::timeout(5)->post('https://exp.host/--/api/v2/push/send', $messages);
        } catch (\Throwable $e) {
            // Push failure must not block payment proof submission.
        }
    }

    private function keuanganRules(bool $required = true): array
    {
        $base = $required ? ['required'] : ['sometimes', 'required'];

        return [
            'kos_id' => [...$base, 'integer'],
            'kategori_id' => ['nullable', 'integer'],
            'jenis' => [...$base, Rule::in(['pemasukan', 'pengeluaran'])],
            'tanggal' => [...$base, 'date'],
            'nominal' => [...$base, 'integer', 'min:0'],
            'keterangan' => ['nullable', 'string'],
        ];
    }

    private function pengumumanRules(bool $required = true): array
    {
        $base = $required ? ['required'] : ['sometimes', 'required'];

        return [
            'kos_id' => [...$base, 'integer'],
            'judul' => [...$base, 'string', 'max:255'],
            'isi' => [...$base, 'string'],
            'status' => [...$base, Rule::in(['aktif', 'nonaktif'])],
        ];
    }

    private function facilityFields(): array
    {
        return ['fasilitas_ac', 'fasilitas_km_dalam', 'fasilitas_wifi', 'fasilitas_kasur', 'fasilitas_lemari', 'fasilitas_meja', 'fasilitas_parkir'];
    }

    private function createBills($penghunis, int $bulan, int $tahun, ?string $tanggalJatuhTempo): int
    {
        $total = 0;

        foreach ($penghunis as $penghuni) {
            $dueDate = $tanggalJatuhTempo ?? $this->dueDateForPenghuni($penghuni, $bulan, $tahun);
            $fee = $this->activeQrisMethod((int) $penghuni->kos_id) ? $this->qrisFee((int) $penghuni->harga_bulanan) : 0;
            $existing = DB::table('tagihans')
                ->where('penghuni_id', $penghuni->penghuni_id)
                ->where('bulan', $bulan)
                ->where('tahun', $tahun)
                ->first();

            if ($existing) {
                DB::table('tagihans')->where('id', $existing->id)->update([
                    'nominal' => $penghuni->harga_bulanan,
                    'biaya_platform' => $fee,
                    'total_dibayar' => (int) $penghuni->harga_bulanan + $fee,
                    'tanggal_jatuh_tempo' => $dueDate,
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('tagihans')->insert([
                    'penghuni_id' => $penghuni->penghuni_id,
                    'bulan' => $bulan,
                    'tahun' => $tahun,
                    'kos_id' => $penghuni->kos_id,
                    'kamar_id' => $penghuni->kamar_id,
                    'nominal' => $penghuni->harga_bulanan,
                    'biaya_platform' => $fee,
                    'total_dibayar' => (int) $penghuni->harga_bulanan + $fee,
                    'tanggal_jatuh_tempo' => $dueDate,
                    'status' => 'belum_lunas',
                    'updated_at' => now(),
                    'created_at' => now(),
                ]);
            }
            $total++;
        }

        return $total;
    }

    private function dueDateForPenghuni($penghuni, int $bulan, int $tahun): string
    {
        $fallbackDay = (int) date('d', strtotime($penghuni->tanggal_masuk ?? now()->toDateString()));
        $dueDay = min(28, max(1, (int) ($penghuni->jatuh_tempo_hari ?: $fallbackDay)));

        return now()->setDate($tahun, $bulan, $dueDay)->toDateString();
    }

    private function nextDueInfo($penghuni): array
    {
        $current = now()->startOfMonth();
        $due = \Illuminate\Support\Carbon::parse($this->dueDateForPenghuni($penghuni, (int) $current->month, (int) $current->year))->startOfDay();

        if ($due->lt(now()->startOfDay())) {
            $next = $current->copy()->addMonth();
            $due = \Illuminate\Support\Carbon::parse($this->dueDateForPenghuni($penghuni, (int) $next->month, (int) $next->year))->startOfDay();
        }

        return [
            'date' => $due->toDateString(),
            'month' => (int) $due->month,
            'year' => (int) $due->year,
            'carbon' => $due,
        ];
    }

    public function autoGenerateForKos(int $kosId, int $daysBeforeDue = 7): int
    {
        $start = now()->startOfDay();
        $end = now()->startOfDay()->addDays($daysBeforeDue);
        $total = 0;

        $penghunis = DB::table('penghunis')
            ->join('kamars', 'kamars.id', '=', 'penghunis.kamar_id')
            ->where('penghunis.kos_id', $kosId)
            ->where('penghunis.status', 'aktif')
            ->select('penghunis.id as penghuni_id', 'penghunis.kos_id', 'penghunis.kamar_id', 'penghunis.jatuh_tempo_hari', 'penghunis.tanggal_masuk', 'kamars.harga_bulanan')
            ->get();

        foreach ($penghunis as $penghuni) {
            $nextDue = $this->nextDueInfo($penghuni);

            if ($nextDue['carbon']->lt($start) || $nextDue['carbon']->gt($end)) {
                continue;
            }

            $total += $this->createBills(collect([$penghuni]), $nextDue['month'], $nextDue['year'], $nextDue['date']);
        }

        return $total;
    }
}
