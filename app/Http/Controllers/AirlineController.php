<?php

namespace App\Http\Controllers;

use App\Models\Airline;
use App\Models\Aircraft;
use Inertia\Inertia;

class AirlineController extends Controller
{
    // METHOD BARU: Untuk halaman List Semua Maskapai
    public function index()
    {
        // Ambil semua data maskapai
        $airlines = Airline::all();

        return Inertia::render('AirlineIndex', [
            'airlines' => $airlines
        ]);
    }

    // METHOD SEBELUMNYA: Untuk halaman Detail
    public function show($code)
    {
        // Panggil data maskapai BESERTA relasi armadanya
        $airline = Airline::with('aircrafts')->where('code', $code)->firstOrFail();

        // Sekarang fleets murni ngambil dari relasi database yang udah kita attach di seeder
        $fleets = $airline->aircrafts;

        return Inertia::render('AirlineDetail', [
            'airline' => $airline,
            'fleets' => $fleets
        ]);
    }
}
