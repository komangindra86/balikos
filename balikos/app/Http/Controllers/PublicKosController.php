<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PublicKosController extends Controller
{
    public function index(Request $request)
    {
        $facilities = array_values(array_intersect(
            (array) $request->input('fasilitas', []),
            ['ac', 'km_dalam', 'wifi', 'kasur', 'lemari', 'meja', 'parkir', 'dapur_dalam']
        ));

        $facilityColumns = [
            'ac' => 'fasilitas_ac', 'km_dalam' => 'fasilitas_km_dalam',
            'wifi' => 'fasilitas_wifi', 'kasur' => 'fasilitas_kasur',
            'lemari' => 'fasilitas_lemari', 'meja' => 'fasilitas_meja',
            'parkir' => 'fasilitas_parkir', 'dapur_dalam' => 'fasilitas_dapur_dalam',
        ];

        $query = DB::table('kos')
            ->join('kamars', 'kamars.kos_id', '=', 'kos.id')
            ->leftJoin('users', 'users.id', '=', 'kos.owner_id')
            ->leftJoin('kamar_fotos', function ($join) {
                $join->on('kamar_fotos.kamar_id', '=', 'kamars.id')->where('kamar_fotos.urutan', 1);
            })
            ->where('kos.status', 'aktif')
            ->where('kamars.status', 'kosong');

        if ($search = trim((string) $request->input('q'))) {
            $query->where(function ($q) use ($search) {
                $q->where('kos.nama_kos', 'like', "%{$search}%")
                    ->orWhere('kos.alamat', 'like', "%{$search}%")
                    ->orWhere('kos.kecamatan', 'like', "%{$search}%")
                    ->orWhere('kos.desa', 'like', "%{$search}%");
            });
        }
        if ($request->filled('lokasi')) $query->where('kos.kecamatan', $request->input('lokasi'));
        if ($request->filled('harga_min')) $query->where('kamars.harga_bulanan', '>=', (int) $request->input('harga_min'));
        if ($request->filled('harga_max')) $query->where('kamars.harga_bulanan', '<=', (int) $request->input('harga_max'));
        foreach ($facilities as $facility) $query->where('kamars.'.$facilityColumns[$facility], true);

        $kos = $query->select([
                'kos.id', 'kos.nama_kos', 'kos.alamat', 'kos.kecamatan', 'kos.desa', 'kos.banjar',
                'kos.latitude', 'kos.longitude', 'kos.no_wa', 'kos.aturan_kos',
                'users.name as owner_name',
                DB::raw('MIN(kamars.harga_bulanan) as harga_mulai'),
                DB::raw('COUNT(DISTINCT kamars.id) as kamar_tersedia'),
                DB::raw('MAX(kamar_fotos.path) as foto'),
                DB::raw('MAX(kamars.fasilitas_ac) as fasilitas_ac'),
                DB::raw('MAX(kamars.fasilitas_km_dalam) as fasilitas_km_dalam'),
                DB::raw('MAX(kamars.fasilitas_wifi) as fasilitas_wifi'),
                DB::raw('MAX(kamars.fasilitas_kasur) as fasilitas_kasur'),
                DB::raw('MAX(kamars.fasilitas_lemari) as fasilitas_lemari'),
                DB::raw('MAX(kamars.fasilitas_meja) as fasilitas_meja'),
                DB::raw('MAX(kamars.fasilitas_parkir) as fasilitas_parkir'),
                DB::raw('MAX(kamars.fasilitas_dapur_dalam) as fasilitas_dapur_dalam'),
            ])
            ->groupBy('kos.id', 'kos.nama_kos', 'kos.alamat', 'kos.kecamatan', 'kos.desa', 'kos.banjar', 'kos.latitude', 'kos.longitude', 'kos.no_wa', 'kos.aturan_kos', 'users.name')
            ->orderBy('harga_mulai')
            ->paginate(9)->withQueryString();

        $locations = DB::table('kos')->where('status', 'aktif')->whereNotNull('kecamatan')
            ->distinct()->orderBy('kecamatan')->pluck('kecamatan');

        return view('public.home', compact('kos', 'locations', 'facilities'));
    }
}
