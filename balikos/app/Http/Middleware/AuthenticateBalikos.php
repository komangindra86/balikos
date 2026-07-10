<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateBalikos
{
    public function handle(Request $request, Closure $next): Response
    {
        $userId = $request->session()->get('balikos_user_id');

        if (! $userId) {
            return redirect()->route('login')->with('error', 'Silakan login terlebih dahulu.');
        }

        $user = DB::table('users')
            ->select('id', 'name', 'email', 'phone', 'role', 'status')
            ->where('id', $userId)
            ->first();

        if (! $user || $user->status !== 'aktif') {
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('error', 'Akun tidak aktif atau tidak ditemukan.');
        }

        view()->share('authUser', $user);
        $request->attributes->set('auth_user', $user);

        return $next($request);
    }
}
