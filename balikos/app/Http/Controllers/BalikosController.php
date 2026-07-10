<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class BalikosController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->attributes->get('auth_user');
        $kosIds = $this->kosQueryFor($user)->pluck('kos.id');
        $bulanIni = (int) now()->month;
        $tahunIni = (int) now()->year;

        $totalKamar = DB::table('kamars')->whereIn('kos_id', $kosIds)->count();
        $kamarTerisi = DB::table('kamars')->whereIn('kos_id', $kosIds)->where('status', 'terisi')->count();
        $totalTagihanBulanIni = DB::table('tagihans')->whereIn('kos_id', $kosIds)->where('bulan', $bulanIni)->where('tahun', $tahunIni)->sum('nominal');
        $totalTagihanLunas = DB::table('tagihans')->whereIn('kos_id', $kosIds)->where('status', 'lunas')->sum('nominal');
        $totalTunggakan = DB::table('tagihans')->whereIn('kos_id', $kosIds)->whereIn('status', ['belum_lunas', 'terlambat'])->sum('nominal');

        $stats = [
            'total_pemilik' => $user->role === 'pemilik_kos' ? 1 : DB::table('users')->where('role', 'pemilik_kos')->count(),
            'total_kos' => $kosIds->count(),
            'total_kamar' => $totalKamar,
            'total_kamar_terisi' => $kamarTerisi,
            'total_kamar_kosong' => DB::table('kamars')->whereIn('kos_id', $kosIds)->where('status', 'kosong')->count(),
            'total_kamar_maintenance' => DB::table('kamars')->whereIn('kos_id', $kosIds)->where('status', 'maintenance')->count(),
            'total_penghuni_aktif' => DB::table('penghunis')->whereIn('kos_id', $kosIds)->where('status', 'aktif')->count(),
            'total_tagihan_bulan_ini' => $totalTagihanBulanIni,
            'total_tagihan_lunas' => $totalTagihanLunas,
            'total_tunggakan' => $totalTunggakan,
            'harga_rata_rata' => (int) DB::table('kamars')->whereIn('kos_id', $kosIds)->avg('harga_bulanan'),
            'okupansi' => $totalKamar > 0 ? round(($kamarTerisi / $totalKamar) * 100, 1) : 0,
        ];

        $recentKos = $this->kosListingQuery($user)
            ->orderByDesc('kos.id')
            ->limit(6)
            ->get();

        return view('balikos.index', compact('stats', 'recentKos'));
    }

    public function pemilik(Request $request): View
    {
        $this->authorizePlatformRole($request);

        $owners = DB::table('users')
            ->leftJoin('kos', 'kos.owner_id', '=', 'users.id')
            ->where('users.role', 'pemilik_kos')
            ->select(
                'users.id',
                'users.name',
                'users.email',
                'users.phone',
                'users.status',
                DB::raw('COUNT(kos.id) as total_kos')
            )
            ->groupBy('users.id', 'users.name', 'users.email', 'users.phone', 'users.status')
            ->orderBy('users.name')
            ->get();

        return view('balikos.pemilik.index', compact('owners'));
    }

    public function showPemilik(Request $request, int $id): View
    {
        $this->authorizePlatformRole($request);

        $owner = $this->findOwner($id);
        $kos = $this->ownerKosSummary($id)->get();

        $kosIds = $kos->pluck('id');
        $summary = [
            'total_kos' => $kos->count(),
            'total_kamar' => DB::table('kamars')->whereIn('kos_id', $kosIds)->count(),
            'penghuni_aktif' => DB::table('penghunis')->whereIn('kos_id', $kosIds)->where('status', 'aktif')->count(),
            'tunggakan' => DB::table('tagihans')->whereIn('kos_id', $kosIds)->whereIn('status', ['belum_lunas', 'terlambat'])->sum('nominal'),
        ];

        return view('balikos.pemilik.show', compact('owner', 'kos', 'summary'));
    }

    public function kos(Request $request): View
    {
        $user = $request->attributes->get('auth_user');
        $filters = $request->only(['kecamatan', 'desa', 'banjar', 'status']);

        $kos = $this->kosListingQuery($user, $filters)
            ->orderBy('kos.nama_kos')
            ->get();

        $filterOptions = $this->filterOptions($user);

        return view('balikos.kos', compact('kos', 'filters', 'filterOptions'));
    }

    public function indeksHarga(Request $request): View
    {
        $user = $request->attributes->get('auth_user');
        $filters = $request->only([
            'kecamatan',
            'desa',
            'banjar',
            'harga_min',
            'harga_max',
            'fasilitas_ac',
            'fasilitas_km_dalam',
            'fasilitas_wifi',
            'fasilitas_kasur',
            'fasilitas_lemari',
            'fasilitas_meja',
            'fasilitas_parkir',
        ]);

        $kosIds = $this->filteredKosIds($user, $filters);
        $roomQuery = DB::table('kamars')
            ->join('kos', 'kos.id', '=', 'kamars.kos_id')
            ->whereIn('kamars.kos_id', $kosIds);

        $this->applyRoomFilters($roomQuery, $filters);

        $rows = $roomQuery
            ->select(
                'kos.kecamatan',
                DB::raw('COUNT(kamars.id) as jumlah_kamar'),
                DB::raw("SUM(CASE WHEN kamars.status = 'terisi' THEN 1 ELSE 0 END) as kamar_terisi"),
                DB::raw('MIN(kamars.harga_bulanan) as harga_min'),
                DB::raw('MAX(kamars.harga_bulanan) as harga_max'),
                DB::raw('AVG(kamars.harga_bulanan) as harga_rata_rata')
            )
            ->groupBy('kos.kecamatan')
            ->orderBy('kos.kecamatan')
            ->get()
            ->map(function ($row) {
                $row->okupansi = $row->jumlah_kamar > 0 ? round(($row->kamar_terisi / $row->jumlah_kamar) * 100, 1) : 0;
                return $row;
            });

        $filterOptions = $this->filterOptions($user);
        $facilityFilters = [
            'fasilitas_ac' => 'AC',
            'fasilitas_km_dalam' => 'KM Dalam',
            'fasilitas_wifi' => 'WiFi',
            'fasilitas_kasur' => 'Kasur',
            'fasilitas_lemari' => 'Lemari',
            'fasilitas_meja' => 'Meja',
            'fasilitas_parkir' => 'Parkir',
        ];

        return view('balikos.indeks-harga', compact('rows', 'filters', 'filterOptions', 'facilityFilters'));
    }

    public function laporan(Request $request): View
    {
        $this->authorizePlatformRole($request);

        $growth = DB::table('kos')
            ->select(DB::raw("DATE_FORMAT(created_at, '%Y-%m') as bulan"), DB::raw('COUNT(*) as total_kos'))
            ->groupBy(DB::raw("DATE_FORMAT(created_at, '%Y-%m')"))
            ->orderBy('bulan')
            ->get();

        $roomGrowth = DB::table('kamars')
            ->select(DB::raw("DATE_FORMAT(created_at, '%Y-%m') as bulan"), DB::raw('COUNT(*) as total_kamar'))
            ->groupBy(DB::raw("DATE_FORMAT(created_at, '%Y-%m')"))
            ->orderBy('bulan')
            ->get();

        $highestPriceAreas = DB::table('kamars')
            ->join('kos', 'kos.id', '=', 'kamars.kos_id')
            ->select('kos.kecamatan', DB::raw('AVG(kamars.harga_bulanan) as harga_rata_rata'))
            ->groupBy('kos.kecamatan')
            ->orderByDesc('harga_rata_rata')
            ->limit(5)
            ->get();

        $highestOccupancyAreas = DB::table('kamars')
            ->join('kos', 'kos.id', '=', 'kamars.kos_id')
            ->select(
                'kos.kecamatan',
                DB::raw('COUNT(kamars.id) as total_kamar'),
                DB::raw("SUM(CASE WHEN kamars.status = 'terisi' THEN 1 ELSE 0 END) as kamar_terisi")
            )
            ->groupBy('kos.kecamatan')
            ->get()
            ->map(function ($row) {
                $row->okupansi = $row->total_kamar > 0 ? round(($row->kamar_terisi / $row->total_kamar) * 100, 1) : 0;
                return $row;
            })
            ->sortByDesc('okupansi')
            ->take(5);

        return view('balikos.laporan', compact('growth', 'roomGrowth', 'highestPriceAreas', 'highestOccupancyAreas'));
    }

    public function penarikan(Request $request): View
    {
        $this->authorizePlatformRole($request);

        $status = $request->input('status');
        $query = DB::table('kos_wallet_withdrawals')
            ->join('kos', 'kos.id', '=', 'kos_wallet_withdrawals.kos_id')
            ->join('users', 'users.id', '=', 'kos.owner_id')
            ->select(
                'kos_wallet_withdrawals.*',
                'kos.nama_kos',
                'users.name as owner_name',
                'users.email as owner_email',
                'users.phone as owner_phone'
            )
            ->orderByRaw("CASE kos_wallet_withdrawals.status WHEN 'menunggu' THEN 0 WHEN 'diproses' THEN 1 WHEN 'ditolak' THEN 2 ELSE 3 END")
            ->orderByDesc('kos_wallet_withdrawals.id');

        if (in_array($status, ['menunggu', 'diproses', 'selesai', 'ditolak'], true)) {
            $query->where('kos_wallet_withdrawals.status', $status);
        }

        $withdrawals = $query->get();
        $summary = [
            'menunggu' => DB::table('kos_wallet_withdrawals')->where('status', 'menunggu')->count(),
            'diproses' => DB::table('kos_wallet_withdrawals')->where('status', 'diproses')->count(),
            'selesai' => DB::table('kos_wallet_withdrawals')->where('status', 'selesai')->count(),
            'ditolak' => DB::table('kos_wallet_withdrawals')->where('status', 'ditolak')->count(),
            'nominal_menunggu' => DB::table('kos_wallet_withdrawals')->whereIn('status', ['menunggu', 'diproses'])->sum('nominal'),
        ];

        return view('balikos.penarikan', compact('withdrawals', 'summary', 'status'));
    }

    public function updatePenarikan(Request $request, int $id)
    {
        $this->authorizePlatformRole($request);

        $data = $request->validate([
            'status' => ['required', 'in:diproses,selesai,ditolak'],
            'catatan' => ['nullable', 'string', 'max:1000'],
        ]);

        $withdrawal = DB::table('kos_wallet_withdrawals')->where('id', $id)->first();
        abort_if(! $withdrawal, 404);
        abort_if($withdrawal->status === 'selesai', 422, 'Penarikan yang sudah selesai tidak dapat diubah.');

        DB::transaction(function () use ($withdrawal, $data, $id) {
            if ($data['status'] === 'ditolak' && $withdrawal->status !== 'ditolak') {
                DB::table('kos_wallets')->where('id', $withdrawal->wallet_id)->update([
                    'saldo_tersedia' => DB::raw('saldo_tersedia + '.(int) $withdrawal->nominal),
                    'total_ditarik' => DB::raw('GREATEST(total_ditarik - '.(int) $withdrawal->nominal.', 0)'),
                    'updated_at' => now(),
                ]);
            }

            DB::table('kos_wallet_withdrawals')->where('id', $id)->update([
                'status' => $data['status'],
                'catatan' => $data['catatan'] ?? $withdrawal->catatan,
                'diproses_at' => in_array($data['status'], ['diproses', 'selesai'], true) ? now() : $withdrawal->diproses_at,
                'updated_at' => now(),
            ]);
        });

        return redirect()->route('balikos.penarikan')->with('success', 'Status penarikan berhasil diperbarui.');
    }

    private function kosQueryFor(object $user)
    {
        $query = DB::table('kos');

        if ($user->role === 'pemilik_kos') {
            $query->where('kos.owner_id', $user->id);
        }

        return $query;
    }

    private function kosListingQuery(object $user, array $filters = [])
    {
        $query = $this->kosQueryFor($user)
            ->leftJoin('users', 'users.id', '=', 'kos.owner_id')
            ->leftJoin('kamars', 'kamars.kos_id', '=', 'kos.id')
            ->select(
                'kos.id',
                'kos.nama_kos',
                'kos.alamat',
                'kos.kecamatan',
                'kos.desa',
                'kos.banjar',
                'kos.status',
                'users.name as owner_name',
                DB::raw('COUNT(kamars.id) as jumlah_kamar'),
                DB::raw("SUM(CASE WHEN kamars.status = 'terisi' THEN 1 ELSE 0 END) as kamar_terisi"),
                DB::raw("SUM(CASE WHEN kamars.status = 'kosong' THEN 1 ELSE 0 END) as kamar_kosong"),
                DB::raw('AVG(kamars.harga_bulanan) as harga_rata_rata')
            )
            ->groupBy('kos.id', 'kos.nama_kos', 'kos.alamat', 'kos.kecamatan', 'kos.desa', 'kos.banjar', 'kos.status', 'users.name');

        foreach (['kecamatan', 'desa', 'banjar', 'status'] as $field) {
            if (! empty($filters[$field])) {
                $query->where('kos.'.$field, $filters[$field]);
            }
        }

        return $query;
    }

    private function filteredKosIds(object $user, array $filters)
    {
        $query = $this->kosQueryFor($user);

        foreach (['kecamatan', 'desa', 'banjar'] as $field) {
            if (! empty($filters[$field])) {
                $query->where('kos.'.$field, $filters[$field]);
            }
        }

        return $query->pluck('kos.id');
    }

    private function applyRoomFilters($query, array $filters): void
    {
        if (! empty($filters['harga_min'])) {
            $query->where('kamars.harga_bulanan', '>=', (int) $filters['harga_min']);
        }

        if (! empty($filters['harga_max'])) {
            $query->where('kamars.harga_bulanan', '<=', (int) $filters['harga_max']);
        }

        foreach (['fasilitas_ac', 'fasilitas_km_dalam', 'fasilitas_wifi', 'fasilitas_kasur', 'fasilitas_lemari', 'fasilitas_meja', 'fasilitas_parkir'] as $field) {
            if (! empty($filters[$field])) {
                $query->where('kamars.'.$field, true);
            }
        }
    }

    private function filterOptions(object $user): array
    {
        $kosQuery = $this->kosQueryFor($user);

        return [
            'kecamatan' => (clone $kosQuery)->whereNotNull('kecamatan')->distinct()->orderBy('kecamatan')->pluck('kecamatan'),
            'desa' => (clone $kosQuery)->whereNotNull('desa')->distinct()->orderBy('desa')->pluck('desa'),
            'banjar' => (clone $kosQuery)->whereNotNull('banjar')->distinct()->orderBy('banjar')->pluck('banjar'),
        ];
    }

    private function findOwner(int $id)
    {
        $owner = DB::table('users')
            ->where('id', $id)
            ->where('role', 'pemilik_kos')
            ->first();

        abort_if(! $owner, 404, 'Pemilik kos tidak ditemukan.');

        return $owner;
    }

    private function ownerKosSummary(int $ownerId)
    {
        return DB::table('kos')
            ->leftJoin('kamars', 'kamars.kos_id', '=', 'kos.id')
            ->where('kos.owner_id', $ownerId)
            ->select(
                'kos.id',
                'kos.nama_kos',
                'kos.kecamatan',
                'kos.desa',
                'kos.banjar',
                'kos.status',
                DB::raw('COUNT(kamars.id) as jumlah_kamar'),
                DB::raw("SUM(CASE WHEN kamars.status = 'terisi' THEN 1 ELSE 0 END) as kamar_terisi")
            )
            ->groupBy('kos.id', 'kos.nama_kos', 'kos.kecamatan', 'kos.desa', 'kos.banjar', 'kos.status')
            ->orderBy('kos.nama_kos');
    }

    private function authorizePlatformRole(Request $request): void
    {
        $user = $request->attributes->get('auth_user');

        if (! in_array($user->role, ['superadmin', 'admin_balikos'], true)) {
            abort(403, 'Hanya superadmin dan admin BALIKOS yang dapat mengakses halaman ini.');
        }
    }
}
