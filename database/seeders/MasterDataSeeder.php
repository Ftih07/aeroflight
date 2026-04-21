<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Aircraft;
use App\Models\BaggageAddon;
use App\Models\Flight;
use Carbon\Carbon;
use Illuminate\Support\Str;

class MasterDataSeeder extends Seeder
{
    public function run()
    {
        // ==========================================
        // 1. DATA PESAWAT (LENGKAP DGN FIRST CLASS & EXIT ROW)
        // ==========================================
        $aircrafts = [
            // 1. The Monster Purple (Superjumbo)
            ['model_name' => 'Airbus A380-800 (Superjumbo)', 'seat_layout' => ['config' => '3-4-3', 'rows' => 45, 'business_rows' => 5, 'first_class_rows' => 2, 'exit_rows' => [15, 28]]],

            // 2. Wide-body (Pesawat Lebar Lorong Ganda)
            ['model_name' => 'Boeing 777-300ER', 'seat_layout' => ['config' => '3-4-3', 'rows' => 35, 'business_rows' => 4, 'first_class_rows' => 1, 'exit_rows' => [12, 24]]],
            ['model_name' => 'Airbus A350-900', 'seat_layout' => ['config' => '3-3-3', 'rows' => 32, 'business_rows' => 4, 'first_class_rows' => 0, 'exit_rows' => [14, 25]]],
            ['model_name' => 'Boeing 787-9 Dreamliner', 'seat_layout' => ['config' => '3-3-3', 'rows' => 30, 'business_rows' => 4, 'first_class_rows' => 0, 'exit_rows' => [10, 22]]],
            ['model_name' => 'Airbus A330-300', 'seat_layout' => ['config' => '2-4-2', 'rows' => 33, 'business_rows' => 4, 'first_class_rows' => 0, 'exit_rows' => [14, 26]]],

            // 3. Narrow-body (Pesawat Komersial Standar Lorong Tunggal)
            ['model_name' => 'Boeing 737-800', 'seat_layout' => ['config' => '3-3', 'rows' => 20, 'business_rows' => 3, 'first_class_rows' => 0, 'exit_rows' => [10, 11]]],
            ['model_name' => 'Airbus A320neo', 'seat_layout' => ['config' => '3-3', 'rows' => 18, 'business_rows' => 2, 'first_class_rows' => 0, 'exit_rows' => [11, 12]]],
            ['model_name' => 'Boeing 737 MAX 8', 'seat_layout' => ['config' => '3-3', 'rows' => 22, 'business_rows' => 3, 'first_class_rows' => 0, 'exit_rows' => [12, 13]]],

            // 4. Regional Jet & Turboprop (Pesawat Kecil Baling-baling)
            ['model_name' => 'Embraer E190', 'seat_layout' => ['config' => '2-2', 'rows' => 20, 'business_rows' => 2, 'first_class_rows' => 0, 'exit_rows' => [10]]],
            ['model_name' => 'ATR 72-600 (Propeller)', 'seat_layout' => ['config' => '2-2', 'rows' => 15, 'business_rows' => 0, 'first_class_rows' => 0, 'exit_rows' => [1]]],
            ['model_name' => 'Bombardier Dash 8', 'seat_layout' => ['config' => '2-2', 'rows' => 18, 'business_rows' => 0, 'first_class_rows' => 0, 'exit_rows' => [1]]],
        ];

        foreach ($aircrafts as $ac) {
            Aircraft::create($ac);
        }

        // ==========================================
        // 2. DATA HARGA EKSTRA BAGASI
        // ==========================================
        $baggages = [
            ['airline_code' => 'DEFAULT', 'weight_kg' => 5, 'price_usd' => 15.00],
            ['airline_code' => 'DEFAULT', 'weight_kg' => 10, 'price_usd' => 25.00],
            ['airline_code' => 'DEFAULT', 'weight_kg' => 20, 'price_usd' => 40.00],
            ['airline_code' => 'DEFAULT', 'weight_kg' => 30, 'price_usd' => 75.00], // Tambahan sultan
        ];

        foreach ($baggages as $baggage) {
            BaggageAddon::create($baggage);
        }

        // ==========================================
        // 3. DATA 10 JADWAL PENERBANGAN INTERNAL (DGN TRANSIT & HARGA DINAMIS)
        // ==========================================
        $now = Carbon::now();

        // Template Harga berdasarkan Maskapai (Bisa disesuaikan)
        $premiumPricing = ['first_class' => 250, 'business' => 100, 'exit_row' => 40, 'window' => 20];
        $standardPricing = ['first_class' => 150, 'business' => 50, 'exit_row' => 25, 'window' => 15];
        $budgetPricing = ['first_class' => 0, 'business' => 30, 'exit_row' => 15, 'window' => 5];

        $flights = [
            // Direct Flights (transits: kosong)
            ['airline' => 'GA', 'flight_num' => 'GA-112', 'ori' => 'CGK', 'dest' => 'DPS', 'price' => 120, 'days_add' => 1, 'hours_add' => 2, 'transits' => [], 'pricing' => $standardPricing],
            ['airline' => 'QG', 'flight_num' => 'QG-880', 'ori' => 'SUB', 'dest' => 'CGK', 'price' => 85, 'days_add' => 1, 'hours_add' => 1.5, 'transits' => [], 'pricing' => $budgetPricing],
            ['airline' => 'SQ', 'flight_num' => 'SQ-950', 'ori' => 'CGK', 'dest' => 'SIN', 'price' => 250, 'days_add' => 2, 'hours_add' => 1.5, 'transits' => [], 'pricing' => $premiumPricing],
            ['airline' => 'JT', 'flight_num' => 'JT-330', 'ori' => 'CGK', 'dest' => 'KNO', 'price' => 95, 'days_add' => 5, 'hours_add' => 2.5, 'transits' => [], 'pricing' => $budgetPricing],

            // Transit Flights (transits: ada isinya)
            ['airline' => 'JL', 'flight_num' => 'JL-725', 'ori' => 'CGK', 'dest' => 'NRT', 'price' => 600, 'days_add' => 3, 'hours_add' => 10, 'transits' => ['SIN'], 'pricing' => $premiumPricing], // Transit di Singapore
            ['airline' => 'EK', 'flight_num' => 'EK-358', 'ori' => 'CGK', 'dest' => 'LHR', 'price' => 750, 'days_add' => 4, 'hours_add' => 17, 'transits' => ['DXB'], 'pricing' => $premiumPricing], // Transit di Dubai
            ['airline' => 'ID', 'flight_num' => 'ID-6112', 'ori' => 'UPG', 'dest' => 'CGK', 'price' => 105, 'days_add' => 7, 'hours_add' => 2, 'transits' => [], 'pricing' => $standardPricing],
            ['airline' => 'GA', 'flight_num' => 'GA-88', 'ori' => 'CGK', 'dest' => 'AMS', 'price' => 1200, 'days_add' => 10, 'hours_add' => 16, 'transits' => ['KNO'], 'pricing' => $standardPricing], // Transit Medan
            ['airline' => 'MH', 'flight_num' => 'MH-711', 'ori' => 'KUL', 'dest' => 'CGK', 'price' => 150, 'days_add' => 2, 'hours_add' => 2, 'transits' => [], 'pricing' => $standardPricing],
            ['airline' => 'QZ', 'flight_num' => 'QZ-201', 'ori' => 'DPS', 'dest' => 'KUL', 'price' => 110, 'days_add' => 5, 'hours_add' => 3, 'transits' => [], 'pricing' => $budgetPricing],
        ];

        $aircraftIds = Aircraft::pluck('id')->toArray();

        foreach ($flights as $f) {
            $departure = $now->copy()->addDays($f['days_add'])->setHour(rand(6, 20))->setMinute(0)->setSecond(0);
            $arrival = $departure->copy()->addMinutes($f['hours_add'] * 60);

            Flight::create([
                'aircraft_id' => $aircraftIds[array_rand($aircraftIds)],
                'flight_number' => $f['flight_num'],
                'airline_code' => $f['airline'],
                'provider' => 'internal',
                'provider_flight_id' => 'INT-' . strtoupper(Str::random(6)),
                'origin_airport' => $f['ori'],
                'destination_airport' => $f['dest'],
                'departure_at' => $departure,
                'arrival_at' => $arrival,
                'base_price_usd' => $f['price'],
                'free_baggage_kg' => 20,
                'transits' => $f['transits'],       // DATA TRANSIT
                'seat_prices' => $f['pricing']      // DATA HARGA DINAMIS
            ]);
        }
    }
}
