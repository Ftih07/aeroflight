<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

use function Symfony\Component\Clock\now;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Akun Customer (Naufal Fathi)
        User::create([
            'name' => 'Naufal Fathi',
            'email' => 'naufalfathi37@gmail.com',
            'password' => Hash::make('12345678'),
            'role' => 'customer',
            'email_verified_at' => now(),
        ]);

        // 2. Akun Admin
        User::create([
            'name' => 'AeroFlight Admin',
            'email' => 'admin@aeroflight.test',
            'password' => Hash::make('password123'),
            'role' => 'admin',
        ]);

        $this->call([
            MasterDataSeeder::class,
        ]);
    }
}
