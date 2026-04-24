<?php

namespace App\Http\Controllers;

use App\Models\Flight;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Services\DuffelService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class FlightController extends Controller
{
    // Bikin private function pembantu biar gampang dipanggil buat berangkat & pulang
    private function fetchFlights($origin, $destination, $date, DuffelService $duffelService)
    {
        if (!$origin || !$destination || !$date) return collect([]);

        // Cari dari Internal
        $internalFlights = \App\Models\Flight::with('aircraft')
            ->where('provider', 'internal')
            ->where('origin_airport', $origin)
            ->where('destination_airport', $destination)
            ->whereDate('departure_at', $date)
            ->orderBy('departure_at')
            ->get();

        // Cari dari Duffel
        $duffelFlights = collect([]);
        $allAircrafts = \App\Models\Aircraft::all();
        $rawDuffelOffers = $duffelService->searchFlights($origin, $destination, $date);

        if ($rawDuffelOffers && $allAircrafts->isNotEmpty()) {
            $duffelFlights = collect($rawDuffelOffers)->map(function ($offer) use ($allAircrafts) {
                $segments = $offer['slices'][0]['segments'];
                $firstSegment = $segments[0];
                $lastSegment = end($segments);

                $airlineCode = $offer['owner']['iata_code'];
                $flightNum = $firstSegment['operating_carrier_flight_number'];
                $departAt = Carbon::parse($firstSegment['departing_at'])->format('Y-m-d H:i:s');

                // 👇 1. BIKIN STABLE ID (Biar nggak berubah tiap kali search)
                $stableFlightId = "DUF-{$airlineCode}-{$flightNum}-" . strtotime($departAt);

                // 👇 2. CEK DATABASE LOKAL (Apakah jadwal ini udah pernah di-generate & disimpen?)
                $existingFlight = \App\Models\Flight::with('aircraft')
                    ->where('provider', 'duffel')
                    ->where('provider_flight_id', $stableFlightId)
                    ->first();

                // Kalau udah ada di DB, pake data pesawat & harga yang lama biar KONSISTEN!
                if ($existingFlight) {
                    return [
                        'id' => $existingFlight->id,
                        'provider' => 'duffel',
                        'provider_flight_id' => $existingFlight->provider_flight_id,
                        'airline_code' => $existingFlight->airline_code,
                        'flight_number' => $existingFlight->flight_number,
                        'origin_airport' => $existingFlight->origin_airport,
                        'destination_airport' => $existingFlight->destination_airport,
                        'departure_at' => $existingFlight->departure_at,
                        'arrival_at' => $existingFlight->arrival_at,
                        'base_price_usd' => $offer['total_amount'], // Base price tetep ngikutin harga live API
                        'free_baggage_kg' => $existingFlight->free_baggage_kg,
                        'cabin_baggage_kg' => $existingFlight->cabin_baggage_kg,
                        'stop_count' => $existingFlight->stop_count,
                        'transits' => $existingFlight->transits,
                        'seat_prices' => $existingFlight->seat_prices,
                        'facilities' => $existingFlight->facilities,
                        'is_refundable' => $existingFlight->is_refundable,
                        'is_reschedulable' => $existingFlight->is_reschedulable,
                        'aircraft_id' => $existingFlight->aircraft_id,
                        'aircraft' => $existingFlight->aircraft,
                    ];
                }

                // 👇 3. JIKA BELUM ADA DI DB, BARU KITA RANDOM (Pesawat, Fasilitas, Bagasi, Transit)
                $transitAirports = [];
                for ($i = 0; $i < count($segments) - 1; $i++) {
                    $transitAirports[] = [
                        'airport' => $segments[$i]['destination']['iata_code'],
                        // UBAH: Random durasi transit dari 45 menit ke 360 menit
                        'duration_minutes' => rand(45, 360)
                    ];
                }

                $randomAircraft = $allAircrafts->random();

                // UBAH: Pilihan bagasi untuk di random
                $cabinBaggageOptions = [7, 10];
                $freeBaggageOptions = [15, 20, 25, 30];

                return [
                    'id' => null,
                    'provider' => 'duffel',
                    'provider_flight_id' => $stableFlightId, // <-- PAKE STABLE ID!
                    'airline_code' => $airlineCode,
                    'flight_number' => $flightNum,
                    'origin_airport' => $firstSegment['origin']['iata_code'],
                    'destination_airport' => $lastSegment['destination']['iata_code'],
                    'departure_at' => $firstSegment['departing_at'],
                    'arrival_at' => $lastSegment['arriving_at'],
                    'base_price_usd' => $offer['total_amount'],

                    // UBAH: Terapkan random bagasinya di sini
                    'free_baggage_kg' => $freeBaggageOptions[array_rand($freeBaggageOptions)],
                    'cabin_baggage_kg' => $cabinBaggageOptions[array_rand($cabinBaggageOptions)],

                    'stop_count' => count($transitAirports),
                    'transits' => $transitAirports,
                    'seat_prices' => [
                        'first_class' => rand(100, 300),
                        'business' => rand(50, 150),
                        'exit_row' => rand(20, 60),
                        'window' => rand(10, 40),
                    ],
                    'facilities' => [
                        'meal' => (bool) rand(0, 1),
                        'wifi' => (bool) rand(0, 1),
                        'entertainment' => (bool) rand(0, 1),
                        'power_usb' => (bool) rand(0, 1),
                    ],
                    'is_refundable' => (bool) rand(0, 1),
                    'is_reschedulable' => (bool) rand(0, 1),
                    'aircraft_id' => $randomAircraft->id,
                    'aircraft' => $randomAircraft
                ];
            });
        }

        return $internalFlights->concat($duffelFlights);
    }

    public function search(Request $request, DuffelService $duffelService)
    {
        $origin = strtoupper($request->input('origin'));
        $destination = strtoupper($request->input('destination'));
        $date = $request->input('date');

        // Parameter Baru
        $tripType = $request->input('trip_type', 'one_way');
        $returnDate = $request->input('return_date');

        // Cari Penerbangan Berangkat
        $outboundFlights = $this->fetchFlights($origin, $destination, $date, $duffelService);

        // Cari Penerbangan Pulang (Jika Round Trip)
        $returnFlights = collect([]);
        if ($tripType === 'round_trip' && $returnDate) {
            $returnFlights = $this->fetchFlights($destination, $origin, $returnDate, $duffelService);
        }

        return Inertia::render('Flights/Search', [
            'flights' => $outboundFlights, // Tetap pakai nama 'flights' utk keberangkatan
            'returnFlights' => $returnFlights, // Data baru untuk kepulangan
            'filters' => $request->only(['origin', 'destination', 'date', 'trip_type', 'return_date'])
        ]);
    }

    public function selectExternalFlight(Request $request)
    {
        $flightData = $request->input('flight');
        $aircraftId = $request->input('aircraft_id'); // TANGKAP ID DARI VUE

        $flight = Flight::updateOrCreate(
            [
                'provider' => 'duffel',
                'provider_flight_id' => $flightData['provider_flight_id'],
            ],
            [
                'airline_code' => $flightData['airline_code'],
                'flight_number' => $flightData['flight_number'],
                'origin_airport' => $flightData['origin_airport'],
                'destination_airport' => $flightData['destination_airport'],
                'departure_at' => Carbon::parse($flightData['departure_at'])->format('Y-m-d H:i:s'),
                'arrival_at' => Carbon::parse($flightData['arrival_at'])->format('Y-m-d H:i:s'),
                'base_price_usd' => $flightData['base_price_usd'],
                'free_baggage_kg' => 20,
                'transits' => $flightData['transits'] ?? [],
                'seat_prices' => $flightData['seat_prices'] ?? null,

                // GUNAKAN ID YANG DIKIRIM DARI SEARCH PAGE
                'aircraft_id' => $aircraftId,
            ]
        );

        return redirect()->route('flights.seats', ['flight' => $flight->id]);
    }
}
