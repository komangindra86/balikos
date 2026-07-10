<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->attributes->get('auth_user');
        $kosScope = DB::table('kos');

        if ($user->role === 'pemilik_kos') {
            $kosScope->where('owner_id', $user->id);
        }

        $kosIds = (clone $kosScope)->pluck('id');

        $stats = [
            'total_kos' => $kosIds->count(),
            'total_kamar' => DB::table('kamars')->whereIn('kos_id', $kosIds)->count(),
            'kamar_terisi' => DB::table('kamars')->whereIn('kos_id', $kosIds)->where('status', 'terisi')->count(),
            'penghuni_aktif' => DB::table('penghunis')->whereIn('kos_id', $kosIds)->where('status', 'aktif')->count(),
        ];

        $latestKos = (clone $kosScope)
            ->leftJoin('kamars', 'kamars.kos_id', '=', 'kos.id')
            ->select('kos.id', 'kos.nama_kos', 'kos.kecamatan', 'kos.desa', 'kos.status', DB::raw('COUNT(kamars.id) as jumlah_kamar'))
            ->groupBy('kos.id', 'kos.nama_kos', 'kos.kecamatan', 'kos.desa', 'kos.status')
            ->orderByDesc('kos.id')
            ->limit(8)
            ->get();

        return view('dashboard.index', compact('stats', 'latestKos'));
    }
}
