<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateBalikosApi
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! $token) {
            return response()->json(['message' => 'Token tidak ditemukan.'], 401);
        }

        $apiToken = DB::table('api_tokens')
            ->where('token_hash', hash('sha256', $token))
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->first();

        if (! $apiToken) {
            return response()->json(['message' => 'Token tidak valid.'], 401);
        }

        $user = DB::table('users')
            ->select('id', 'name', 'email', 'phone', 'role', 'status')
            ->where('id', $apiToken->user_id)
            ->where('status', 'aktif')
            ->first();

        if (! $user || $user->role !== 'pemilik_kos') {
            return response()->json(['message' => 'Akun tidak diizinkan mengakses API pemilik kos.'], 403);
        }

        DB::table('api_tokens')->where('id', $apiToken->id)->update(['last_used_at' => now()]);
        $request->attributes->set('auth_user', $user);
        $request->attributes->set('api_token_id', $apiToken->id);

        return $next($request);
    }
}
