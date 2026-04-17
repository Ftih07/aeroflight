<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

class SmartPasswordConfirm
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        // Bypass: Kalau dia daftar via Google dan passwordnya kosong
        if ($user && $user->google_id && empty($user->password)) {
            // Beri penanda di session bahwa dia sudah "terkonfirmasi" saat login via Google tadi
            $request->session()->put('auth.password_confirmed_at', time());
            return $next($request);
        }

        // Kalau user biasa (punya password), jalankan middleware password.confirm bawaan Laravel
        return app(\Illuminate\Auth\Middleware\RequirePassword::class)->handle($request, $next);
    }
}
