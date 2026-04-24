<?php

namespace App\Http\Controllers;

use App\Models\Flight;
use App\Models\Booking;
use App\Models\Passenger;
use App\Models\Seat;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Stripe\Stripe;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Mail\BookingConfirmed;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class CheckoutController extends Controller
{
    // --- HELPER: SIMPAN TIKET DUFFEL KE LOKAL ---
    private function ensureFlightExists($flightId, $externalData)
    {
        // Kalau flightId berupa angka dan bukan dari duffel, langsung return dari DB
        if (!$externalData) {
            return Flight::findOrFail($flightId);
        }

        // Kalau dari Duffel, simpan atau update ke database lokal
        return Flight::updateOrCreate(
            [
                'provider' => 'duffel',
                // Pastikan dia mencari menggunakan Stable ID yang dilempar dari frontend
                'provider_flight_id' => $externalData['provider_flight_id']
            ],
            [
                'airline_code' => $externalData['airline_code'],
                'flight_number' => $externalData['flight_number'],
                'origin_airport' => $externalData['origin_airport'],
                'destination_airport' => $externalData['destination_airport'],
                'departure_at' => Carbon::parse($externalData['departure_at'])->format('Y-m-d H:i:s'),
                'arrival_at' => Carbon::parse($externalData['arrival_at'])->format('Y-m-d H:i:s'),
                'base_price_usd' => $externalData['base_price_usd'],
                'free_baggage_kg' => $externalData['free_baggage_kg'] ?? 20,
                'cabin_baggage_kg' => $externalData['cabin_baggage_kg'] ?? 7,
                'stop_count' => $externalData['stop_count'] ?? count($externalData['transits'] ?? []),
                'transits' => $externalData['transits'] ?? [],
                'facilities' => $externalData['facilities'],
                'is_refundable' => $externalData['is_refundable'],
                'is_reschedulable' => $externalData['is_reschedulable'],
                'seat_prices' => $externalData['seat_prices'],
                'aircraft_id' => $externalData['aircraft_id'] ?? 1,
            ]
        );
    }

    // --- HELPER: GENERATE SEAT MAP ---
    private function generateSeatMap($flight)
    {
        // 1. Amankan Layout Data (Ubah string JSON ke Array kalau perlu)
        $layoutData = $flight->aircraft->seat_layout ?? ["config" => "3-3", "rows" => 10, "business_rows" => 2];
        if (is_string($layoutData)) {
            $layoutData = json_decode($layoutData, true);
        }

        $bookedSeats = $flight->seats->pluck('seat_code')->toArray();
        $groupedSeats = [];
        $columns = explode('-', $layoutData['config']);
        $totalSeatsPerRow = array_sum($columns);
        $alphabet = range('A', 'Z');
        $exitRows = $layoutData['exit_rows'] ?? [];

        // 2. Amankan Seat Prices (INI BIANG KEROKNYA KEMARIN)
        $prices = $flight->seat_prices;
        if (is_string($prices)) {
            $prices = json_decode($prices, true);
        }

        // Fallback jaga-jaga kalau JSON kosong/error
        if (!is_array($prices) || empty($prices)) {
            $prices = ['first_class' => 150, 'business' => 50, 'exit_row' => 25, 'window' => 15];
        }

        for ($r = 1; $r <= $layoutData['rows']; $r++) {
            $rowSeats = [];
            $colIndex = 0;

            $businessRows = $layoutData['business_rows'] ?? 0;
            $firstClassRows = $layoutData['first_class_rows'] ?? 0;

            if ($r <= $firstClassRows) {
                $class = 'first_class';
                $baseAdditional = $prices['first_class'] ?? 150;
            } elseif ($r <= ($firstClassRows + $businessRows)) {
                $class = 'business';
                $baseAdditional = $prices['business'] ?? 50;
            } else {
                $class = 'economy';
                $baseAdditional = 0.00;
            }

            $isExitRow = in_array($r, $exitRows);
            if ($isExitRow && $class === 'economy') $baseAdditional += ($prices['exit_row'] ?? 25);

            foreach ($columns as $groupIndex => $groupSize) {
                for ($i = 0; $i < $groupSize; $i++) {
                    $seatCode = $r . $alphabet[$colIndex];
                    $isWindow = ($colIndex === 0 || $colIndex === ($totalSeatsPerRow - 1));
                    $isAisle = ($i === 0 && $groupIndex !== 0) || ($i === ($groupSize - 1) && $groupIndex !== (count($columns) - 1));

                    $finalPrice = $baseAdditional;
                    if ($isWindow && $class === 'economy') $finalPrice += ($prices['window'] ?? 15);

                    $rowSeats[] = [
                        'id' => $seatCode,
                        'seat_code' => $seatCode,
                        'class' => $class,
                        'is_window' => $isWindow,
                        'is_aisle' => $isAisle,
                        'is_exit_row' => $isExitRow,
                        'additional_price_usd' => $finalPrice,
                        'is_available' => !in_array($seatCode, $bookedSeats)
                    ];
                    $colIndex++;
                }
                if ($groupIndex < count($columns) - 1) {
                    $rowSeats[] = ['id' => 'aisle_' . $r . '_' . $groupIndex, 'is_aisle_space' => true];
                }
            }
            $groupedSeats[$r] = $rowSeats;
        }
        return $groupedSeats;
    }

    // 1. INIT BOOKING
    public function initBooking(Request $request)
    {
        // Validasi disederhanakan. Hapus 'exists:flights,id' karena tiket Duffel ID-nya masih null sebelum disave.
        $request->validate([
            'trip_type' => 'required|in:one_way,round_trip',
        ]);

        $outboundFlight = $this->ensureFlightExists($request->outbound_flight_id, $request->outbound_flight_data);

        $parentBooking = Booking::create([
            'user_id' => Auth::id(),
            'flight_id' => $outboundFlight->id,
            'total_amount_usd' => $outboundFlight->base_price_usd,
            'status' => 'draft',
        ]);

        if ($request->trip_type === 'round_trip') {
            $returnFlight = $this->ensureFlightExists($request->return_flight_id, $request->return_flight_data);
            Booking::create([
                'user_id' => Auth::id(),
                'flight_id' => $returnFlight->id,
                'parent_booking_id' => $parentBooking->id,
                'total_amount_usd' => $returnFlight->base_price_usd,
                'status' => 'draft',
            ]);
        }

        return redirect()->route('bookings.passengers', ['booking_session' => $parentBooking->id]);
    }

    // 2. PASSENGER FORM
    public function passengerForm($booking_session)
    {
        $parentBooking = Booking::with(['flight.aircraft'])->findOrFail($booking_session);
        $childBooking = Booking::with(['flight.aircraft'])->where('parent_booking_id', $parentBooking->id)->first();

        // Inject Airline Info
        $airlinesMap = Cache::remember('openflights_airlines', 86400, function () {
            $response = Http::withoutVerifying()->get('https://raw.githubusercontent.com/jpatokal/openflights/master/data/airlines.dat');
            if ($response->successful()) {
                $map = [];
                foreach (explode("\n", $response->body()) as $line) {
                    $cols = str_getcsv($line);
                    if (count($cols) > 3 && !empty($cols[3]) && $cols[3] !== '\N' && $cols[3] !== '-') $map[$cols[3]] = $cols[1];
                }
                return $map;
            }
            return [];
        });

        $parentBooking->flight->airline_name = $airlinesMap[$parentBooking->flight->airline_code] ?? $parentBooking->flight->airline_code;
        if ($childBooking) $childBooking->flight->airline_name = $airlinesMap[$childBooking->flight->airline_code] ?? $childBooking->flight->airline_code;

        // 👇 TAMBAHAN BARU: Ambil Addon Bagasi berdasarkan kode maskapai
        $baggageAddons = \App\Models\BaggageAddon::whereIn('airline_code', [
            $parentBooking->flight->airline_code,
            $childBooking ? $childBooking->flight->airline_code : 'DEFAULT' // Fallback ke DEFAULT kalau ga nemu
        ])->orWhere('airline_code', 'DEFAULT')->get();

        return Inertia::render('Flights/PassengerForm', [
            'booking_session' => $parentBooking->id,
            'outbound_flight' => $parentBooking->flight,
            'return_flight' => $childBooking ? $childBooking->flight : null,
            'trip_type' => $childBooking ? 'round_trip' : 'one_way',
            'baggage_addons' => $baggageAddons // 👇 LEMPAR KE VUE
        ]);
    }

    // 3. SELECT SEAT
    public function selectSeat(Request $request, $booking_session)
    {
        $passengers = $request->input('passengers'); // Ambil dari Vue state form

        $parentBooking = Booking::with(['flight.aircraft'])->findOrFail($booking_session);
        $childBooking = Booking::with(['flight.aircraft'])->where('parent_booking_id', $parentBooking->id)->first();

        $outboundSeats = $this->generateSeatMap($parentBooking->flight);
        $returnSeats = $childBooking ? $this->generateSeatMap($childBooking->flight) : null;

        return Inertia::render('Flights/SelectSeat', [
            'booking_session' => $parentBooking->id,
            'trip_type' => $childBooking ? 'round_trip' : 'one_way',
            'passengers' => $passengers,
            'outbound_flight' => $parentBooking->flight,
            'outbound_seats' => $outboundSeats,
            'return_flight' => $childBooking ? $childBooking->flight : null,
            'return_seats' => $returnSeats,
        ]);
    }

    // 4. FINAL CHECKOUT (PAYMENT STRIPE)
    public function checkout(Request $request, $booking_session)
    {
        $parentBooking = Booking::with('flight')->findOrFail($booking_session);
        $childBooking = Booking::with('flight')->where('parent_booking_id', $parentBooking->id)->first();

        return DB::transaction(function () use ($request, $parentBooking, $childBooking) {
            $totalParentPrice = 0;
            $totalChildPrice = 0;

            foreach ($request->passengers as $p) {
                $baggageFee = $p['baggage_fee_usd'] ?? 0;

                // Kalkulasi Tiket Berangkat
                $outSeat = $p['outbound_seat'];
                $totalParentPrice += $parentBooking->flight->base_price_usd + $baggageFee + ($outSeat['additional_price_usd'] ?? 0);

                Passenger::create([
                    'booking_id' => $parentBooking->id,
                    'seat_code' => $outSeat['seat_code'],
                    'title' => $p['title'],
                    'first_name' => $p['first_name'],
                    'last_name' => $p['last_name'],
                    'date_of_birth' => $p['date_of_birth'],
                    'nationality' => $p['nationality'],
                    'passport_number' => $p['passport_number'] ?? null,
                    'extra_baggage_kg' => $p['extra_baggage_kg'] ?? 0,
                    'baggage_fee_usd' => $baggageFee,
                ]);

                Seat::create([
                    'flight_id' => $parentBooking->flight_id,
                    'seat_code' => $outSeat['seat_code'],
                    'class' => $outSeat['class'],
                    'additional_price_usd' => $outSeat['additional_price_usd'] ?? 0,
                ]);

                // Kalkulasi Tiket Pulang (Jika ada)
                if ($childBooking && isset($p['return_seat'])) {
                    $retSeat = $p['return_seat'];
                    $totalChildPrice += $childBooking->flight->base_price_usd + ($retSeat['additional_price_usd'] ?? 0);

                    Passenger::create([
                        'booking_id' => $childBooking->id,
                        'seat_code' => $retSeat['seat_code'],
                        'title' => $p['title'],
                        'first_name' => $p['first_name'],
                        'last_name' => $p['last_name'],
                        'date_of_birth' => $p['date_of_birth'],
                        'nationality' => $p['nationality'],
                        'passport_number' => $p['passport_number'] ?? null,
                        'extra_baggage_kg' => 0, // Bagasi kepulangan dipisah jika ingin, ini diset 0 untuk simpel
                        'baggage_fee_usd' => 0,
                    ]);

                    Seat::create([
                        'flight_id' => $childBooking->flight_id,
                        'seat_code' => $retSeat['seat_code'],
                        'class' => $retSeat['class'],
                        'additional_price_usd' => $retSeat['additional_price_usd'] ?? 0,
                    ]);
                }
            }

            // Update Total dan Status
            $grandTotal = $totalParentPrice + $totalChildPrice;
            $parentBooking->update(['total_amount_usd' => $totalParentPrice, 'status' => 'pending']);
            if ($childBooking) $childBooking->update(['total_amount_usd' => $totalChildPrice, 'status' => 'pending']);

            // Init Stripe
            Stripe::setApiKey(env('STRIPE_SECRET'));
            $paymentIntent = \Stripe\PaymentIntent::create([
                'amount' => $grandTotal * 100,
                'currency' => 'usd',
                'description' => 'AeroFlight Booking - ' . $parentBooking->flight->origin_airport . ' to ' . $parentBooking->flight->destination_airport . ($childBooking ? ' (Round Trip)' : ''),
                'metadata' => ['parent_booking_id' => $parentBooking->id],
                'receipt_email' => Auth::user()->email,
            ]);

            $parentBooking->update(['stripe_payment_id' => $paymentIntent->id]);
            if ($childBooking) $childBooking->update(['stripe_payment_id' => $paymentIntent->id]);

            return Inertia::render('Flights/Payment', [
                'flight' => $parentBooking->flight, // Menampilkan data utama untuk UI payment
                'return_flight' => $childBooking ? $childBooking->flight : null, // <-- TAMBAHKAN BARIS INI
                'booking' => $parentBooking->load('passengers'),
                'grandTotal' => $grandTotal,
                'isRoundTrip' => (bool) $childBooking,
                'clientSecret' => $paymentIntent->client_secret,
                'stripeKey' => env('STRIPE_KEY')
            ]);
        });
    }

    // 4.5 CONTINUE PAYMENT (Dari halaman History)
    public function continuePayment(Booking $booking)
    {
        // Pastikan hanya booking pending yang bisa dibayar ulang
        if ($booking->status !== 'pending') {
            return redirect()->route('bookings.history')->with('error', 'Booking ini sudah tidak dapat dibayar.');
        }

        // Load relasi yang dibutuhkan oleh view Payment
        $booking->load(['flight', 'passengers']);

        // Cari tiket kepulangan jika ini Round Trip
        $childBooking = Booking::with('flight')->where('parent_booking_id', $booking->id)->first();

        // Hitung Grand Total
        $grandTotal = $booking->total_amount_usd + ($childBooking ? $childBooking->total_amount_usd : 0);

        Stripe::setApiKey(env('STRIPE_SECRET'));

        try {
            // Coba panggil ulang Payment Intent yang lama dari Stripe
            $paymentIntent = \Stripe\PaymentIntent::retrieve($booking->stripe_payment_id);
            $clientSecret = $paymentIntent->client_secret;
        } catch (\Exception $e) {
            // Jika Payment Intent lama gagal ditarik (mungkin expired), generate ulang yang baru
            $paymentIntent = \Stripe\PaymentIntent::create([
                'amount' => $grandTotal * 100,
                'currency' => 'usd',
                'description' => 'AeroFlight Booking - ' . $booking->flight->origin_airport . ' to ' . $booking->flight->destination_airport . ($childBooking ? ' (Round Trip)' : ''),
                'metadata' => ['parent_booking_id' => $booking->id],
                'receipt_email' => Auth::user()->email,
            ]);

            // Update ID di database lokal kita
            $booking->update(['stripe_payment_id' => $paymentIntent->id]);
            if ($childBooking) {
                $childBooking->update(['stripe_payment_id' => $paymentIntent->id]);
            }

            $clientSecret = $paymentIntent->client_secret;
        }

        // Render ulang komponen Payment.vue yang sudah kita buat
        return Inertia::render('Flights/Payment', [
            'flight' => $booking->flight,
            'return_flight' => $childBooking ? $childBooking->flight : null,
            'booking' => $booking,
            'grandTotal' => $grandTotal,
            'isRoundTrip' => (bool) $childBooking,
            'clientSecret' => $clientSecret,
            'stripeKey' => env('STRIPE_KEY')
        ]);
    }

    // 5. SUCCESS
    public function success(Request $request)
    {
        Stripe::setApiKey(env('STRIPE_SECRET'));
        $paymentIntent = \Stripe\PaymentIntent::retrieve($request->payment_intent);

        if ($paymentIntent->status !== 'succeeded') {
            return redirect()->route('dashboard')->with('error', 'Pembayaran gagal atau belum selesai.');
        }

        $parentBooking = Booking::with(['flight.aircraft', 'passengers'])->find($paymentIntent->metadata->parent_booking_id);
        $childBooking = Booking::with(['flight.aircraft', 'passengers'])->where('parent_booking_id', $parentBooking->id)->first();

        // 👇 TAMBAHAN: Ambil Cache Nama Kota Bandara
        $airportsMap = Cache::remember('airports_data_map', 86400, function () {
            $response = Http::withoutVerifying()->get('https://gist.githubusercontent.com/tdreyno/4278655/raw/7b0762c09b519f40397e4c3e100b097d861f5588/airports.json');
            $map = [];
            if ($response->successful()) {
                foreach ($response->json() as $airport) {
                    if (!empty($airport['code'])) {
                        $map[$airport['code']] = $airport['city'];
                    }
                }
            }
            return $map;
        });

        // 👇 SUNTIK DATA KOTA KE FLIGHT
        if ($parentBooking) {
            $parentBooking->flight->origin_city = $airportsMap[$parentBooking->flight->origin_airport] ?? 'CITY N/A';
            $parentBooking->flight->destination_city = $airportsMap[$parentBooking->flight->destination_airport] ?? 'CITY N/A';
        }
        if ($childBooking) {
            $childBooking->flight->origin_city = $airportsMap[$childBooking->flight->origin_airport] ?? 'CITY N/A';
            $childBooking->flight->destination_city = $airportsMap[$childBooking->flight->destination_airport] ?? 'CITY N/A';
        }

        if ($parentBooking && $parentBooking->status === 'pending') {
            $pnrParent = strtoupper(Str::random(6));
            // GENERATE QR TOKEN UNTUK OUTBOUND
            $qrParent = 'AERO-' . $pnrParent . '-' . Str::random(10);

            $parentBooking->update(['status' => 'paid', 'pnr_code' => $pnrParent, 'qr_token' => $qrParent]);
            $parentBooking->transactions()->create(['type' => 'payment', 'amount' => $parentBooking->total_amount_usd, 'description' => 'Stripe Payment (Outbound)']);

            if ($childBooking) {
                $pnrChild = strtoupper(Str::random(6));
                // GENERATE QR TOKEN UNTUK RETURN
                $qrChild = 'AERO-' . $pnrChild . '-' . Str::random(10);

                $childBooking->update(['status' => 'paid', 'pnr_code' => $pnrChild, 'qr_token' => $qrChild]);
                $childBooking->transactions()->create(['type' => 'payment', 'amount' => $childBooking->total_amount_usd, 'description' => 'Stripe Payment (Return)']);
            }

            // Suntik Nama Maskapai khusus PDF
            $airlinesMap = Cache::get('openflights_airlines', []);
            $parentBooking->flight->airline_name = $airlinesMap[$parentBooking->flight->airline_code] ?? $parentBooking->flight->airline_code;
            if ($childBooking) {
                $childBooking->flight->airline_name = $airlinesMap[$childBooking->flight->airline_code] ?? $childBooking->flight->airline_code;
            }

            // Kirim email tiket
            $pdf = Pdf::setOption(['isRemoteEnabled' => true])->loadView('emails.ticket', [
                'booking' => $parentBooking,
                'child_booking' => $childBooking
            ]);
            Mail::to($request->user()->email)->send(new BookingConfirmed($parentBooking, $childBooking, $pdf->output()));
        }

        // Suntik Nama Maskapai buat halaman Vue
        $airlinesMap = Cache::get('openflights_airlines', []);
        $parentBooking->flight->airline_name = $airlinesMap[$parentBooking->flight->airline_code] ?? $parentBooking->flight->airline_code;

        if ($childBooking) {
            $childBooking->flight->airline_name = $airlinesMap[$childBooking->flight->airline_code] ?? $childBooking->flight->airline_code;
        }

        // Lempar juga child_booking ke Vue
        return Inertia::render('Checkout/Success', [
            'booking' => $parentBooking,
            'child_booking' => $childBooking
        ]);
    }
}
