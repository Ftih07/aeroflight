<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class GoogleController extends Controller
{
    // Melempar user ke halaman Login Google
    public function redirect()
    {
        return Socialite::driver('google')->redirect();
    }

    // Menangani kembalian dari Google
    public function callback(Request $request)
    {
        $googleUser = Socialite::driver('google')->user();

        // Cari user berdasarkan email, atau buat baru kalau belum ada
        $user = User::updateOrCreate(
            ['email' => $googleUser->email],
            [
                'name' => $googleUser->name,
                'google_id' => $googleUser->id,
                'google_token' => $googleUser->token,
                'email_verified_at' => now(), // Otomatis verified karena dari Google
            ]
        );

        // CEK 2FA: Apakah user ini mengaktifkan 2FA di aplikasinya?
        if ($user->two_factor_secret) {
            // Jangan login-kan! Simpan ID-nya di session, lalu lempar ke satpam Fortify
            $request->session()->put([
                'login.id' => $user->id,
                'login.remember' => true,
            ]);

            return redirect()->route('two-factor.login');
        }

        // Kalau nggak ada 2FA, langsung login dan masuk ke Dashboard Customer
        Auth::login($user, true);

        return redirect()->route('dashboard');
    }
}
