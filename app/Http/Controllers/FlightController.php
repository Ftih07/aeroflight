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
    private function fetchFlights($origin, $destination, $date, DuffelService $duffelService)
    {
        if (!$origin || !$destination || !$date) return collect([]);

        // 1. CARI DARI INTERNAL (Eager Load Segments & Classes)
        $internalFlights = Flight::with(['segments.classes', 'segments.aircraft', 'segments.airlineData'])
            ->where('provider', 'internal')
            ->where('origin_airport', $origin)
            ->where('destination_airport', $destination)
            ->whereDate('departure_at', $date)
            ->orderBy('departure_at')
            ->get();

        // Append custom attribute agar muncul di frontend Vue
        $internalFlights->each->append('starting_price');

        // 2. CARI DARI DUFFEL
        $duffelFlights = collect([]);
        $allAircrafts = \App\Models\Aircraft::all();
        $rawDuffelOffers = $duffelService->searchFlights($origin, $destination, $date);

        if ($rawDuffelOffers && $allAircrafts->isNotEmpty()) {

            // --- FILTER DATA SAMPAH DARI DUFFEL SEBELUM DI-MAPPING ---
            $validOffers = collect($rawDuffelOffers)->filter(function ($offer) {
                // 1. Harga tidak boleh kosong atau 0
                if (empty($offer['total_amount']) || $offer['total_amount'] <= 0) {
                    return false;
                }

                // 2. Harus punya segment penerbangan
                if (empty($offer['slices'][0]['segments'])) {
                    return false;
                }

                // 3. Maskapai dan Nomor Penerbangan tidak boleh kosong (Cek segment pertama)
                $firstSegment = $offer['slices'][0]['segments'][0];
                if (empty($firstSegment['operating_carrier']['iata_code']) || empty($firstSegment['operating_carrier_flight_number'])) {
                    return false;
                }

                return true; // Lolos filter
            });

            $duffelFlights = $validOffers->map(function ($offer) use ($allAircrafts) {
                $segments = $offer['slices'][0]['segments'];
                $firstSegment = $segments[0];
                $lastSegment = end($segments);

                $airlineCode = $offer['owner']['iata_code'];
                $flightNum = $firstSegment['operating_carrier_flight_number'];
                $departAt = Carbon::parse($firstSegment['departing_at'])->format('Y-m-d H:i:s');

                $stableFlightId = "DUF-{$airlineCode}-{$flightNum}-" . strtotime($departAt);

                // CEK DB LOKAL
                $existingFlight = Flight::with(['segments.classes', 'segments.aircraft', 'segments.airlineData'])
                    ->where('provider', 'duffel')
                    ->where('provider_flight_id', $stableFlightId)
                    ->first();

                if ($existingFlight) {
                    $existingFlight->append('starting_price');
                    return $existingFlight->toArray();
                }

                // JIKA BELUM ADA DI DB, KITA BENTUK STRUKTUR ARRAY YANG SAMA DENGAN ELOQUENT
                $formattedSegments = [];
                $segmentOrder = 1;

                // Kalkulasi kasar harga per segmen (dibagi rata dari total harga Duffel)
                $pricePerSegment = $offer['total_amount'] / count($segments);

                foreach ($segments as $seg) {
                    $randomAircraft = $allAircrafts->random();

                    $formattedSegments[] = [
                        'airline_code' => $seg['operating_carrier']['iata_code'],
                        'flight_number' => $seg['operating_carrier_flight_number'],
                        'origin_airport' => $seg['origin']['iata_code'],
                        'destination_airport' => $seg['destination']['iata_code'],
                        'departure_at' => Carbon::parse($seg['departing_at'])->format('Y-m-d H:i:s'),
                        'arrival_at' => Carbon::parse($seg['arriving_at'])->format('Y-m-d H:i:s'),
                        'segment_order' => $segmentOrder,
                        'aircraft_id' => $randomAircraft->id,
                        'aircraft' => $randomAircraft,
                        // Buatkan Class default (Economy) untuk preview
                        'classes' => [
                            [
                                'class_type' => 'economy',
                                'base_price_usd' => $pricePerSegment,
                                'facilities' => [
                                    'meal' => (bool) rand(0, 1),
                                    'wifi' => (bool) rand(0, 1),
                                    'entertainment' => (bool) rand(0, 1),
                                    'power_usb' => true,
                                ],
                                'cabin_baggage_kg' => 7,
                                'free_baggage_kg' => 20,
                            ]
                        ]
                    ];
                    $segmentOrder++;
                }

                return [
                    'id' => null, // Belum disave ke DB
                    'provider' => 'duffel',
                    'provider_flight_id' => $stableFlightId,
                    'origin_airport' => $firstSegment['origin']['iata_code'],
                    'destination_airport' => $lastSegment['destination']['iata_code'],
                    'departure_at' => $firstSegment['departing_at'],
                    'arrival_at' => $lastSegment['arriving_at'],
                    'stop_count' => count($segments) - 1,
                    'is_refundable' => (bool) rand(0, 1),
                    'is_reschedulable' => (bool) rand(0, 1),
                    'starting_price' => $offer['total_amount'], // Total Harga
                    'segments' => $formattedSegments,
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
        $tripType = $request->input('trip_type', 'one_way');
        $returnDate = $request->input('return_date');

        $outboundFlights = $this->fetchFlights($origin, $destination, $date, $duffelService);

        $returnFlights = collect([]);
        if ($tripType === 'round_trip' && $returnDate) {
            $returnFlights = $this->fetchFlights($destination, $origin, $returnDate, $duffelService);
        }

        return Inertia::render('Flights/Search', [
            'flights' => $outboundFlights,
            'returnFlights' => $returnFlights,
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
