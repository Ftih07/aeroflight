<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Airline;
use App\Models\Aircraft;
use App\Models\BaggageAddon;
use App\Models\Flight;
use App\Models\FlightSegment;
use App\Models\FlightClass;
use App\Models\Seat;
use Carbon\Carbon;
use Illuminate\Support\Str;

class MasterDataSeeder extends Seeder
{
    public function run()
    {
        // ==========================================
        // 0. DATA MASKAPAI (TIDAK BERUBAH)
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
        // 1. DATA PESAWAT (TIDAK BERUBAH)
        // ==========================================
        $aircrafts = [
            ['model_name' => 'Airbus A380-800', 'manufacturer' => 'Airbus', 'description' => 'Pesawat penumpang terbesar di dunia dengan dua lantai.', 'seat_layout' => ['config' => '3-4-3', 'rows' => 45, 'business_rows' => 5, 'first_class_rows' => 2, 'exit_rows' => [15, 28]]],
            ['model_name' => 'Boeing 777-300ER', 'manufacturer' => 'Boeing', 'description' => 'Pesawat badan lebar jarak jauh andalan banyak maskapai.', 'seat_layout' => ['config' => '3-4-3', 'rows' => 35, 'business_rows' => 4, 'first_class_rows' => 1, 'exit_rows' => [12, 24]]],
            ['model_name' => 'Airbus A350-900', 'manufacturer' => 'Airbus', 'description' => 'Pesawat generasi baru yang sangat efisien bahan bakar.', 'seat_layout' => ['config' => '3-3-3', 'rows' => 32, 'business_rows' => 4, 'first_class_rows' => 0, 'exit_rows' => [14, 25]]],
            ['model_name' => 'Boeing 737-800', 'manufacturer' => 'Boeing', 'description' => 'Tulang punggung penerbangan jarak pendek-menengah di dunia.', 'seat_layout' => ['config' => '3-3', 'rows' => 20, 'business_rows' => 3, 'first_class_rows' => 0, 'exit_rows' => [10, 11]]],
            ['model_name' => 'Airbus A320neo', 'manufacturer' => 'Airbus', 'description' => 'Pesaing utama 737 dengan mesin generasi baru yang lebih senyap.', 'seat_layout' => ['config' => '3-3', 'rows' => 18, 'business_rows' => 2, 'first_class_rows' => 0, 'exit_rows' => [11, 12]]],
        ];

        foreach ($aircrafts as $ac) {
            Aircraft::create($ac);
        }

        // ==========================================
        // 2. DATA HARGA EKSTRA BAGASI (DENGAN CLASS TYPE)
        // ==========================================
        $baggages = [
            ['airline_code' => 'DEFAULT', 'class_type' => 'economy', 'weight_kg' => 5, 'price_usd' => 15.00],
            ['airline_code' => 'DEFAULT', 'class_type' => 'economy', 'weight_kg' => 10, 'price_usd' => 25.00],
            ['airline_code' => 'DEFAULT', 'class_type' => 'economy', 'weight_kg' => 20, 'price_usd' => 40.00],
            ['airline_code' => 'DEFAULT', 'class_type' => 'economy', 'weight_kg' => 30, 'price_usd' => 75.00],
        ];

        foreach ($baggages as $baggage) {
            BaggageAddon::create($baggage);
        }

        // ==========================================
        // 3. DATA PENERBANGAN (DIPISAH MENJADI SEGMENT & CLASS)
        // ==========================================
        $now = Carbon::now();

        // Fasilitas dipisah berdasarkan kelas
        $economyFac = ['meal' => true, 'wifi' => false, 'entertainment' => true, 'power_usb' => true];
        $businessFac = ['meal' => true, 'wifi' => true, 'entertainment' => true, 'power_usb' => true, 'lounge_access' => true];
        $firstClassFac = ['meal' => true, 'wifi' => true, 'entertainment' => true, 'power_usb' => true, 'lounge_access' => true, 'private_suite' => true];

        $lccEconomyFac = ['meal' => false, 'wifi' => false, 'entertainment' => false, 'power_usb' => true];

        $flights = [
            // Direct Flights (0 transit)
            ['airline' => 'GA', 'flight_num' => 'GA-112', 'ori' => 'CGK', 'dest' => 'DPS', 'base_price' => 120, 'days_add' => 1, 'hours_add' => 2, 'transits' => [], 'fac' => $economyFac, 'refund' => true, 'reschedule' => true, 'type' => 'full'],
            ['airline' => 'QG', 'flight_num' => 'QG-880', 'ori' => 'SUB', 'dest' => 'CGK', 'base_price' => 85, 'days_add' => 1, 'hours_add' => 1.5, 'transits' => [], 'fac' => $lccEconomyFac, 'refund' => false, 'reschedule' => true, 'type' => 'lcc'],

            // Transit Flights (1 Transit)
            [
                'airline' => 'JL',
                'flight_num' => 'JL-725',
                'ori' => 'CGK',
                'dest' => 'NRT',
                'base_price' => 600,
                'days_add' => 3,
                'hours_add' => 10,
                'fac' => $economyFac,
                'refund' => true,
                'reschedule' => true,
                'type' => 'full',
                'transits' => [
                    ['airport' => 'SIN', 'duration_minutes' => 120]
                ]
            ],
            [
                'airline' => 'EK',
                'flight_num' => 'EK-358',
                'ori' => 'CGK',
                'dest' => 'LHR',
                'base_price' => 750,
                'days_add' => 4,
                'hours_add' => 17,
                'fac' => $economyFac,
                'refund' => true,
                'reschedule' => true,
                'type' => 'full',
                'transits' => [
                    ['airport' => 'DXB', 'duration_minutes' => 180]
                ]
            ],

            // Multi-transit (2 Stops)
            [
                'airline' => 'GA',
                'flight_num' => 'GA-88',
                'ori' => 'CGK',
                'dest' => 'AMS',
                'base_price' => 1200,
                'days_add' => 10,
                'hours_add' => 20,
                'fac' => $economyFac,
                'refund' => true,
                'reschedule' => true,
                'type' => 'full',
                'transits' => [
                    ['airport' => 'KNO', 'duration_minutes' => 90],
                    ['airport' => 'DXB', 'duration_minutes' => 150]
                ]
            ],
        ];

        $aircraftIds = Aircraft::pluck('id')->toArray();

        foreach ($flights as $f) {
            $totalDuration = $f['hours_add'] * 60;
            $departure = $now->copy()->addDays($f['days_add'])->setHour(rand(6, 20))->setMinute(0)->setSecond(0);
            $arrival = $departure->copy()->addMinutes($totalDuration);

            // 1. Buat Induk Flight
            $flight = Flight::create([
                'provider' => 'internal',
                'provider_flight_id' => 'INT-' . strtoupper(Str::random(6)),
                'origin_airport' => $f['ori'],
                'destination_airport' => $f['dest'],
                'departure_at' => $departure,
                'arrival_at' => $arrival,
                'stop_count' => count($f['transits']),
                'is_refundable' => $f['refund'],
                'is_reschedulable' => $f['reschedule'],
            ]);

            // 2. Logika Pemecahan Segmen berdasarkan Transit
            $segmentsData = [];
            $currentOri = $f['ori'];
            $currentDeparture = $departure->copy();

            // Menghitung durasi terbang per segmen secara kasar (dibagi rata dikurangi waktu transit)
            $totalTransitTime = collect($f['transits'])->sum('duration_minutes');
            $flightTimePerSegment = count($f['transits']) > 0
                ? ($totalDuration - $totalTransitTime) / (count($f['transits']) + 1)
                : $totalDuration;

            // Jika ada transit
            if (count($f['transits']) > 0) {
                $order = 1;
                foreach ($f['transits'] as $transit) {
                    $segmentArrival = $currentDeparture->copy()->addMinutes($flightTimePerSegment);

                    $segmentsData[] = [
                        'flight_id' => $flight->id,
                        'aircraft_id' => $aircraftIds[array_rand($aircraftIds)],
                        'airline_code' => $f['airline'],
                        'flight_number' => $f['flight_num'] . '-' . $order, // Contoh: GA-88-1
                        'origin_airport' => $currentOri,
                        'destination_airport' => $transit['airport'],
                        'departure_at' => $currentDeparture->copy(),
                        'arrival_at' => $segmentArrival->copy(),
                        'segment_order' => $order,
                    ];

                    // Update untuk segmen berikutnya
                    $currentOri = $transit['airport'];
                    $currentDeparture = $segmentArrival->addMinutes($transit['duration_minutes']);
                    $order++;
                }

                // Segmen Terakhir (dari transit terakhir ke destinasi akhir)
                $segmentsData[] = [
                    'flight_id' => $flight->id,
                    'aircraft_id' => $aircraftIds[array_rand($aircraftIds)],
                    'airline_code' => $f['airline'],
                    'flight_number' => $f['flight_num'] . '-' . $order,
                    'origin_airport' => $currentOri,
                    'destination_airport' => $f['dest'],
                    'departure_at' => $currentDeparture->copy(),
                    'arrival_at' => $arrival->copy(), // Pastikan tiba sesuai jadwal total
                    'segment_order' => $order,
                ];
            } else {
                // Jika Direct Flight (Hanya 1 Segmen)
                $segmentsData[] = [
                    'flight_id' => $flight->id,
                    'aircraft_id' => $aircraftIds[array_rand($aircraftIds)],
                    'airline_code' => $f['airline'],
                    'flight_number' => $f['flight_num'],
                    'origin_airport' => $f['ori'],
                    'destination_airport' => $f['dest'],
                    'departure_at' => $departure,
                    'arrival_at' => $arrival,
                    'segment_order' => 1,
                ];
            }

            // 3. Masukkan Segmen ke DB & Buat Class per Segmen
            foreach ($segmentsData as $segData) {
                $segment = FlightSegment::create($segData);

                // Buat Kelas Economy (Semua pesawat ada)
                FlightClass::create([
                    'flight_segment_id' => $segment->id,
                    'class_type' => 'economy',
                    'base_price_usd' => $f['base_price'], // Asumsi base price adalah untuk economy
                    'facilities' => $f['fac'],
                    'cabin_baggage_kg' => 7,
                    'free_baggage_kg' => 20,
                ]);

                // Buat Kelas Business & First Class jika maskapai full-service (bukan LCC)
                if ($f['type'] === 'full') {
                    FlightClass::create([
                        'flight_segment_id' => $segment->id,
                        'class_type' => 'business',
                        'base_price_usd' => $f['base_price'] * 2.5, // Business 2.5x lipat harga Economy
                        'facilities' => $businessFac,
                        'cabin_baggage_kg' => 10,
                        'free_baggage_kg' => 30,
                    ]);

                    // Opsional: Buat First Class hanya untuk maskapai tertentu (misal Emirates / JAL)
                    if (in_array($f['airline'], ['EK', 'JL', 'SQ'])) {
                        FlightClass::create([
                            'flight_segment_id' => $segment->id,
                            'class_type' => 'first_class',
                            'base_price_usd' => $f['base_price'] * 5, // First Class 5x lipat harga Economy
                            'facilities' => $firstClassFac,
                            'cabin_baggage_kg' => 15,
                            'free_baggage_kg' => 40,
                        ]);
                    }
                }
            }
        }
    }
}
