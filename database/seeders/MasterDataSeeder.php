<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Airline; // Tambahan Model Airline
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
        // 0. DATA MASKAPAI (LOKAL DB UNTUK EDUKASI)
        // ==========================================
        $airlines = [
            ['code' => 'GA', 'name' => 'Garuda Indonesia', 'description' => 'Maskapai penerbangan nasional Indonesia dengan layanan full-service berbintang 5.'],
            ['code' => 'QG', 'name' => 'Citilink', 'description' => 'Anak perusahaan Garuda Indonesia yang beroperasi sebagai maskapai berbiaya rendah (LCC).'],
            ['code' => 'SQ', 'name' => 'Singapore Airlines', 'description' => 'Salah satu maskapai terbaik di dunia dengan hub utama di Bandara Changi.'],
            ['code' => 'JT', 'name' => 'Lion Air', 'description' => 'Maskapai swasta terbesar di Indonesia dengan jaringan rute domestik yang luas.'],
            ['code' => 'JL', 'name' => 'Japan Airlines', 'description' => 'Maskapai nasional Jepang yang terkenal dengan ketepatan waktu dan pelayanannya.'],
            ['code' => 'EK', 'name' => 'Emirates', 'description' => 'Maskapai raksasa dari Dubai yang mengoperasikan armada Airbus A380 terbanyak di dunia.'],
            ['code' => 'ID', 'name' => 'Batik Air', 'description' => 'Maskapai full-service dari Lion Air Group.'],
            ['code' => 'MH', 'name' => 'Malaysia Airlines', 'description' => 'Maskapai nasional Malaysia yang berpusat di Kuala Lumpur.'],
            ['code' => 'QZ', 'name' => 'Indonesia AirAsia', 'description' => 'Cabang Indonesia dari maskapai berbiaya rendah raksasa Asia Tenggara.'],
        ];

        foreach ($airlines as $airline) {
            Airline::create($airline);
        }

        // ==========================================
        // 1. DATA PESAWAT (LENGKAP DGN FIRST CLASS & EXIT ROW)
        // ==========================================
        $aircrafts = [
            // 1. The Monster Purple (Superjumbo)
            ['model_name' => 'Airbus A380-800', 'manufacturer' => 'Airbus', 'description' => 'Pesawat penumpang terbesar di dunia dengan dua lantai.', 'seat_layout' => ['config' => '3-4-3', 'rows' => 45, 'business_rows' => 5, 'first_class_rows' => 2, 'exit_rows' => [15, 28]]],

            // 2. Wide-body (Pesawat Lebar Lorong Ganda)
            ['model_name' => 'Boeing 777-300ER', 'manufacturer' => 'Boeing', 'description' => 'Pesawat badan lebar jarak jauh andalan banyak maskapai.', 'seat_layout' => ['config' => '3-4-3', 'rows' => 35, 'business_rows' => 4, 'first_class_rows' => 1, 'exit_rows' => [12, 24]]],
            ['model_name' => 'Airbus A350-900', 'manufacturer' => 'Airbus', 'description' => 'Pesawat generasi baru yang sangat efisien bahan bakar.', 'seat_layout' => ['config' => '3-3-3', 'rows' => 32, 'business_rows' => 4, 'first_class_rows' => 0, 'exit_rows' => [14, 25]]],

            // 3. Narrow-body (Pesawat Komersial Standar Lorong Tunggal)
            ['model_name' => 'Boeing 737-800', 'manufacturer' => 'Boeing', 'description' => 'Tulang punggung penerbangan jarak pendek-menengah di dunia.', 'seat_layout' => ['config' => '3-3', 'rows' => 20, 'business_rows' => 3, 'first_class_rows' => 0, 'exit_rows' => [10, 11]]],
            ['model_name' => 'Airbus A320neo', 'manufacturer' => 'Airbus', 'description' => 'Pesaing utama 737 dengan mesin generasi baru yang lebih senyap.', 'seat_layout' => ['config' => '3-3', 'rows' => 18, 'business_rows' => 2, 'first_class_rows' => 0, 'exit_rows' => [11, 12]]],
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
            ['airline_code' => 'DEFAULT', 'weight_kg' => 30, 'price_usd' => 75.00],
        ];

        foreach ($baggages as $baggage) {
            BaggageAddon::create($baggage);
        }

        // ==========================================
        // 3. DATA JADWAL PENERBANGAN INTERNAL
        // ==========================================
        $now = Carbon::now();

        $premiumPricing = ['first_class' => 250, 'business' => 100, 'exit_row' => 40, 'window' => 20];
        $standardPricing = ['first_class' => 150, 'business' => 50, 'exit_row' => 25, 'window' => 15];
        $budgetPricing = ['first_class' => 0, 'business' => 30, 'exit_row' => 15, 'window' => 5];

        // Template Fasilitas
        $fullServiceFac = ['meal' => true, 'wifi' => true, 'entertainment' => true, 'power_usb' => true];
        $lccFac = ['meal' => false, 'wifi' => false, 'entertainment' => false, 'power_usb' => true];

        $flights = [
            // Direct Flights (transits: kosong)
            ['airline' => 'GA', 'flight_num' => 'GA-112', 'ori' => 'CGK', 'dest' => 'DPS', 'price' => 120, 'days_add' => 1, 'hours_add' => 2, 'transits' => [], 'pricing' => $standardPricing, 'fac' => $fullServiceFac, 'refund' => true, 'reschedule' => true],
            ['airline' => 'QG', 'flight_num' => 'QG-880', 'ori' => 'SUB', 'dest' => 'CGK', 'price' => 85, 'days_add' => 1, 'hours_add' => 1.5, 'transits' => [], 'pricing' => $budgetPricing, 'fac' => $lccFac, 'refund' => false, 'reschedule' => true],

            // Transit Flights (Struktur transits diupdate biar lebih detail)
            [
                'airline' => 'JL',
                'flight_num' => 'JL-725',
                'ori' => 'CGK',
                'dest' => 'NRT',
                'price' => 600,
                'days_add' => 3,
                'hours_add' => 10,
                'pricing' => $premiumPricing,
                'fac' => $fullServiceFac,
                'refund' => true,
                'reschedule' => true,
                'transits' => [
                    ['airport' => 'SIN', 'duration_minutes' => 120]
                ]
            ],

            [
                'airline' => 'EK',
                'flight_num' => 'EK-358',
                'ori' => 'CGK',
                'dest' => 'LHR',
                'price' => 750,
                'days_add' => 4,
                'hours_add' => 17,
                'pricing' => $premiumPricing,
                'fac' => $fullServiceFac,
                'refund' => true,
                'reschedule' => true,
                'transits' => [
                    ['airport' => 'DXB', 'duration_minutes' => 180]
                ]
            ],

            // Multi-transit example (2 stops)
            [
                'airline' => 'GA',
                'flight_num' => 'GA-88',
                'ori' => 'CGK',
                'dest' => 'AMS',
                'price' => 1200,
                'days_add' => 10,
                'hours_add' => 20,
                'pricing' => $standardPricing,
                'fac' => $fullServiceFac,
                'refund' => true,
                'reschedule' => true,
                'transits' => [
                    ['airport' => 'KNO', 'duration_minutes' => 90],
                    ['airport' => 'DXB', 'duration_minutes' => 150]
                ]
            ],
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

                // Tambahan Data Baru yang disesuaikan
                'stop_count' => count($f['transits']), // Otomatis ngitung dari jumlah transit
                'transits' => $f['transits'],
                'departure_at' => $departure,
                'arrival_at' => $arrival,
                'base_price_usd' => $f['price'],
                'seat_prices' => $f['pricing'],

                'facilities' => $f['fac'], // Data fasilitas
                'cabin_baggage_kg' => 7, // Default standar kabin
                'free_baggage_kg' => 20,
                'is_refundable' => $f['refund'],
                'is_reschedulable' => $f['reschedule'],
            ]);
        }
    }
}
