<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function showLogin(Request $request)
    {
        if ($request->session()->has('balikos_user_id')) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = DB::table('users')
            ->where('email', $credentials['email'])
            ->where('status', 'aktif')
            ->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return back()
                ->withInput($request->only('email'))
                ->with('error', 'Email atau password tidak sesuai.');
        }

        $request->session()->regenerate();
        $request->session()->put('balikos_user_id', $user->id);

        return redirect()->route('dashboard')->with('success', 'Login berhasil. Selamat datang di Bali Santih.');
    }

    public function logout(Request $request)
    {
        $request->session()->forget('balikos_user_id');
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'Anda berhasil logout.');
    }
}
