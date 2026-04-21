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
    public function search(Request $request, DuffelService $duffelService)
    {
        $origin = strtoupper($request->input('origin'));
        $destination = strtoupper($request->input('destination'));
        $date = $request->input('date');

        // Cari dari Database Internal (Lokal)
        $query = \App\Models\Flight::query()->where('provider', 'internal');

        if ($origin && $destination) {
            $query->where('origin_airport', $origin)
                ->where('destination_airport', $destination);
        }
        if ($date) {
            $query->whereDate('departure_at', $date);
        }
        $internalFlights = $query->with('aircraft')->orderBy('departure_at')->get();

        // Cari dari Duffel API
        $duffelFlights = collect([]);
        $allAircrafts = \App\Models\Aircraft::all();

        if ($origin && $destination && $date) {
            $rawDuffelOffers = $duffelService->searchFlights($origin, $destination, $date);

            if ($rawDuffelOffers && $allAircrafts->isNotEmpty()) {
                $duffelFlights = collect($rawDuffelOffers)->map(function ($offer) use ($allAircrafts) {
                    $segments = $offer['slices'][0]['segments'];
                    $firstSegment = $segments[0];
                    $lastSegment = end($segments);

                    $transitAirports = [];
                    for ($i = 0; $i < count($segments) - 1; $i++) {
                        $transitAirports[] = $segments[$i]['destination']['iata_code'];
                    }

                    $randomAircraft = $allAircrafts->random();

                    return [
                        'id' => null,
                        'provider' => 'duffel',
                        'provider_flight_id' => $offer['id'],
                        'airline_code' => $offer['owner']['iata_code'],
                        'flight_number' => $firstSegment['operating_carrier_flight_number'],
                        'origin_airport' => $firstSegment['origin']['iata_code'],
                        'destination_airport' => $lastSegment['destination']['iata_code'],
                        'departure_at' => $firstSegment['departing_at'],
                        'arrival_at' => $lastSegment['arriving_at'],
                        'base_price_usd' => $offer['total_amount'],
                        'free_baggage_kg' => 20,
                        'transits' => $transitAirports, // Ini murni array IATA transit
                        'seat_prices' => ['first_class' => 150, 'business' => 50, 'exit_row' => 25, 'window' => 15],
                        'aircraft_id' => $randomAircraft->id,
                        'aircraft' => $randomAircraft
                    ];
                });
            }
        }

        $allFlights = $internalFlights->concat($duffelFlights);

        return Inertia::render('Flights/Search', [
            'flights' => $allFlights,
            'filters' => $request->only(['origin', 'destination', 'date'])
        ]);
    }

    public function selectSeat($id)
    {
        $flight = Flight::with(['aircraft', 'seats'])->find($id);

        if (!$flight) {
            abort(404, 'Penerbangan tidak ditemukan di database lokal.');
        }

        $airlinesMap = Cache::remember('openflights_airlines', 86400, function () {
            $response = \Illuminate\Support\Facades\Http::withoutVerifying()->get('https://raw.githubusercontent.com/jpatokal/openflights/master/data/airlines.dat');
            $map = [];
            if ($response->successful()) {
                foreach (explode("\n", $response->body()) as $line) {
                    $cols = str_getcsv($line);
                    if (count($cols) > 3 && !empty($cols[3]) && $cols[3] !== '\N' && $cols[3] !== '-') {
                        $map[$cols[3]] = $cols[1];
                    }
                }
            }
            return $map;
        });

        $flight->airline_name = $airlinesMap[$flight->airline_code] ?? $flight->airline_code;

        // 1. Ambil JSON Layout dari pesawat
        $layoutData = $flight->aircraft->seat_layout ?? [
            "config" => "3-3",
            "rows" => 10,
            "business_rows" => 2
        ];

        // 2. Ambil kursi yang sudah TERBELI/DIKUNCI
        $bookedSeats = $flight->seats->pluck('seat_code')->toArray();

        // 3. Generate struktur kursi untuk dilempar ke Vue
        $groupedSeats = [];
        $columns = explode('-', $layoutData['config']); // cth: ['3', '4', '3']
        $totalSeatsPerRow = array_sum($columns); // Hitung total kursi (cth: 10)
        $alphabet = range('A', 'Z');
        $exitRows = $layoutData['exit_rows'] ?? []; // Ambil data pintu darurat

        // AMBIL HARGA DINAMIS DARI TABEL FLIGHTS (Kalau null, pakai harga default ini)
        $prices = $flight->seat_prices ?? [
            'first_class' => 150,
            'business' => 50,
            'exit_row' => 25,
            'window' => 15
        ];

        for ($r = 1; $r <= $layoutData['rows']; $r++) {
            $rowSeats = [];
            $colIndex = 0;

            // Tentukan Kelas Dasar & Harga dari DB
            $businessRows = $layoutData['business_rows'] ?? 0;
            $firstClassRows = $layoutData['first_class_rows'] ?? 0;

            if ($r <= $firstClassRows) {
                $class = 'first_class';
                $baseAdditional = $prices['first_class']; // Dinamis dari DB
            } elseif ($r <= ($firstClassRows + $businessRows)) {
                $class = 'business';
                $baseAdditional = $prices['business']; // Dinamis dari DB
            } else {
                $class = 'economy';
                $baseAdditional = 0.00;
            }

            // Cek Exit Row (Harga Selonjoran Kaki)
            $isExitRow = in_array($r, $exitRows);
            if ($isExitRow && $class === 'economy') {
                $baseAdditional += $prices['exit_row']; // Dinamis dari DB
            }

            foreach ($columns as $groupIndex => $groupSize) {
                for ($i = 0; $i < $groupSize; $i++) {
                    $seatCode = $r . $alphabet[$colIndex];

                    // Cek Jendela (Paling Kiri atau Paling Kanan)
                    $isWindow = ($colIndex === 0 || $colIndex === ($totalSeatsPerRow - 1));

                    // Cek Lorong (Kursi di pinggir dalam grup)
                    $isAisle = ($i === 0 && $groupIndex !== 0) || ($i === ($groupSize - 1) && $groupIndex !== (count($columns) - 1));

                    $finalPrice = $baseAdditional;
                    if ($isWindow && $class === 'economy') {
                        $finalPrice += $prices['window']; // Dinamis dari DB
                    }

                    $rowSeats[] = [
                        'id' => $seatCode,
                        'seat_code' => $seatCode,
                        'class' => $class,
                        'is_window' => $isWindow,
                        'is_aisle' => $isAisle,
                        'is_exit_row' => $isExitRow,
                        'additional_price_usd' => $finalPrice, // Harga Fix Dinamis
                        'is_available' => !in_array($seatCode, $bookedSeats)
                    ];
                    $colIndex++;
                }

                // THE MAGIC: Masukin "Ruang Kosong" buat jadi Lorong
                if ($groupIndex < count($columns) - 1) {
                    $rowSeats[] = [
                        'id' => 'aisle_' . $r . '_' . $groupIndex,
                        'is_aisle_space' => true // Tanda buat Frontend
                    ];
                }
            }
            $groupedSeats[$r] = $rowSeats;
        }

        return Inertia::render('Flights/SelectSeat', [
            'flight' => $flight,
            'groupedSeats' => $groupedSeats
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
