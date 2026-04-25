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
    // --- HELPER: SIMPAN TIKET DUFFEL KE LOKAL DENGAN SEGMENTS & CLASSES ---
    private function ensureFlightExists($flightId, $externalData)
    {
        if (!$externalData) {
            return Flight::with(['segments.classes', 'segments.aircraft'])->findOrFail($flightId);
        }

        $flight = Flight::firstOrCreate(
            [
                'provider' => 'duffel',
                'provider_flight_id' => $externalData['provider_flight_id']
            ],
            [
                'origin_airport' => $externalData['origin_airport'],
                'destination_airport' => $externalData['destination_airport'],
                'departure_at' => Carbon::parse($externalData['departure_at'])->format('Y-m-d H:i:s'),
                'arrival_at' => Carbon::parse($externalData['arrival_at'])->format('Y-m-d H:i:s'),
                'stop_count' => $externalData['stop_count'] ?? 0,
                'is_refundable' => $externalData['is_refundable'] ?? false,
                'is_reschedulable' => $externalData['is_reschedulable'] ?? false,
            ]
        );

        if ($flight->wasRecentlyCreated && !empty($externalData['segments'])) {
            foreach ($externalData['segments'] as $segData) {
                $segment = $flight->segments()->create([
                    'aircraft_id' => $segData['aircraft_id'] ?? 1, // Fallback ID jika tidak ada
                    'airline_code' => $segData['airline_code'],
                    'flight_number' => $segData['flight_number'],
                    'origin_airport' => $segData['origin_airport'],
                    'destination_airport' => $segData['destination_airport'],
                    'departure_at' => Carbon::parse($segData['departure_at'])->format('Y-m-d H:i:s'),
                    'arrival_at' => Carbon::parse($segData['arrival_at'])->format('Y-m-d H:i:s'),
                    'segment_order' => $segData['segment_order'] ?? 1,
                ]);

                // Simpan kelas ke dalam segmen tersebut
                if (!empty($segData['classes'])) {
                    foreach ($segData['classes'] as $clsData) {
                        $segment->classes()->create([
                            'class_type' => $clsData['class_type'],
                            'base_price_usd' => $clsData['base_price_usd'],
                            'facilities' => $clsData['facilities'] ?? [],
                            'cabin_baggage_kg' => $clsData['cabin_baggage_kg'] ?? 7,
                            'free_baggage_kg' => $clsData['free_baggage_kg'] ?? 20,
                        ]);
                    }
                }
            }
        }

        return $flight->load(['segments.classes', 'segments.aircraft']);
    }

    // --- HELPER: GENERATE SEAT MAP (PER SEGMENT) ---
    private function generateSeatMap($segment)
    {
        $layoutData = $segment->aircraft->seat_layout ?? ["config" => "3-3", "rows" => 10, "business_rows" => 2];
        if (is_string($layoutData)) {
            $layoutData = json_decode($layoutData, true);
        }

        $bookedSeats = \App\Models\Seat::where('flight_segment_id', $segment->id)
            ->whereIn('status', ['booked', 'blocked'])
            ->pluck('seat_code')
            ->toArray();

        $classes = $segment->classes->keyBy('class_type');

        $groupedSeats = [];
        $columns = explode('-', $layoutData['config']);
        $totalSeatsPerRow = array_sum($columns);
        $alphabet = range('A', 'Z');
        $exitRows = $layoutData['exit_rows'] ?? [];

        for ($r = 1; $r <= $layoutData['rows']; $r++) {
            $rowSeats = [];
            $colIndex = 0;

            $businessRows = $layoutData['business_rows'] ?? 0;
            $firstClassRows = $layoutData['first_class_rows'] ?? 0;

            if ($r <= $firstClassRows) {
                $classType = 'first_class';
                $baseAdditional = 150;
            } elseif ($r <= ($firstClassRows + $businessRows)) {
                $classType = 'business';
                $baseAdditional = 50;
            } else {
                $classType = 'economy';
                $baseAdditional = 0;
            }

            $classData = $classes->get($classType) ?? $classes->first();

            $isExitRow = in_array($r, $exitRows);
            if ($isExitRow && $classType === 'economy') $baseAdditional += 25;

            foreach ($columns as $groupIndex => $groupSize) {
                for ($i = 0; $i < $groupSize; $i++) {
                    $seatCode = $r . $alphabet[$colIndex];
                    $isWindow = ($colIndex === 0 || $colIndex === ($totalSeatsPerRow - 1));
                    $isAisle = ($i === 0 && $groupIndex !== 0) || ($i === ($groupSize - 1) && $groupIndex !== (count($columns) - 1));

                    $finalPrice = $baseAdditional;
                    if ($isWindow && $classType === 'economy') $finalPrice += 15;

                    $rowSeats[] = [
                        'id' => $seatCode,
                        'seat_code' => $seatCode,
                        'class' => $classType,
                        'flight_class_id' => $classData ? $classData->id : null,
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
        $request->validate([
            'trip_type' => 'required|in:one_way,round_trip',
        ]);

        $outboundFlight = $this->ensureFlightExists($request->outbound_flight_id, $request->outbound_flight_data);

        $parentBooking = Booking::create([
            'user_id' => Auth::id(),
            'flight_id' => $outboundFlight->id,
            'total_amount_usd' => $outboundFlight->starting_price,
            'status' => 'draft',
        ]);

        if ($request->trip_type === 'round_trip') {
            $returnFlight = $this->ensureFlightExists($request->return_flight_id, $request->return_flight_data);
            Booking::create([
                'user_id' => Auth::id(),
                'flight_id' => $returnFlight->id,
                'parent_booking_id' => $parentBooking->id,
                'total_amount_usd' => $returnFlight->starting_price,
                'status' => 'draft',
            ]);
        }

        return redirect()->route('bookings.passengers', ['booking_session' => $parentBooking->id]);
    }

    // 2. PASSENGER FORM
    public function passengerForm($booking_session)
    {
        $parentBooking = Booking::with(['flight.segments.aircraft', 'flight.segments.airlineData', 'flight.segments.classes'])->findOrFail($booking_session);
        $childBooking = Booking::with(['flight.segments.aircraft', 'flight.segments.airlineData', 'flight.segments.classes'])->where('parent_booking_id', $parentBooking->id)->first();

        $parentBooking->flight->append('starting_price');
        if ($childBooking) $childBooking->flight->append('starting_price');

        $outboundMainAirline = $parentBooking->flight->segments->first()->airline_code ?? 'DEFAULT';
        $returnMainAirline = $childBooking ? ($childBooking->flight->segments->first()->airline_code ?? 'DEFAULT') : 'DEFAULT';

        $baggageAddons = \App\Models\BaggageAddon::whereIn('airline_code', [
            $outboundMainAirline,
            $returnMainAirline
        ])->orWhere('airline_code', 'DEFAULT')->get();

        return Inertia::render('Flights/PassengerForm', [
            'booking_session' => $parentBooking->id,
            'outbound_flight' => $parentBooking->flight,
            'return_flight' => $childBooking ? $childBooking->flight : null,
            'trip_type' => $childBooking ? 'round_trip' : 'one_way',
            'baggage_addons' => $baggageAddons
        ]);
    }

    // 3. SELECT SEAT
    public function selectSeat(Request $request, $booking_session)
    {
        if ($request->isMethod('post')) {
            session(['aero_pax_' . $booking_session => $request->input('passengers')]);
        }

        $passengers = session('aero_pax_' . $booking_session, []);

        if (empty($passengers)) {
            return redirect()->route('bookings.passengers', ['booking_session' => $booking_session]);
        }

        $parentBooking = Booking::with(['flight.segments.aircraft', 'flight.segments.classes'])->findOrFail($booking_session);
        $childBooking = Booking::with(['flight.segments.aircraft', 'flight.segments.classes'])->where('parent_booking_id', $parentBooking->id)->first();

        $parentBooking->flight->append('starting_price');
        if ($childBooking) {
            $childBooking->flight->append('starting_price');
        }

        $outboundSeatsMap = [];
        foreach ($parentBooking->flight->segments as $segment) {
            $outboundSeatsMap[$segment->id] = $this->generateSeatMap($segment);
        }

        $returnSeatsMap = [];
        if ($childBooking) {
            foreach ($childBooking->flight->segments as $segment) {
                $returnSeatsMap[$segment->id] = $this->generateSeatMap($segment);
            }
        }

        return Inertia::render('Flights/SelectSeat', [
            'booking_session' => $parentBooking->id,
            'trip_type' => $childBooking ? 'round_trip' : 'one_way',
            'passengers' => $passengers,
            'outbound_flight' => $parentBooking->flight,
            'outbound_seats_map' => $outboundSeatsMap,
            'return_flight' => $childBooking ? $childBooking->flight : null,
            'return_seats_map' => $returnSeatsMap,
        ]);
    }

    // 4. FINAL CHECKOUT (PAYMENT STRIPE)
    public function checkout(Request $request, $booking_session)
    {
        // FIX ERROR 500: Eager load 'classes' untuk memastikan harga bisa dihitung
        $parentBooking = Booking::with(['flight.segments.classes'])->findOrFail($booking_session);
        $childBooking = Booking::with(['flight.segments.classes'])->where('parent_booking_id', $parentBooking->id)->first();

        $parentBooking->flight->append('starting_price');
        if ($childBooking) {
            $childBooking->flight->append('starting_price');
        }

        return DB::transaction(function () use ($request, $parentBooking, $childBooking) {
            $totalParentPrice = 0;
            $totalChildPrice = 0;

            foreach ($request->passengers as $p) {
                $baggageFee = $p['baggage_fee_usd'] ?? 0;

                $outboundAssignedSeats = [];
                $returnAssignedSeats = [];

                $totalParentPrice += $parentBooking->flight->starting_price + $baggageFee;

                if (isset($p['outbound_seats'])) {
                    foreach ($p['outbound_seats'] as $outSeatSelection) {
                        $segId = $outSeatSelection['segment_id'];
                        $seatData = $outSeatSelection['seat'];

                        $totalParentPrice += ($seatData['additional_price_usd'] ?? 0);
                        $outboundAssignedSeats['segment_' . $segId] = $seatData['seat_code'];

                        Seat::create([
                            'flight_segment_id' => $segId,
                            'flight_class_id' => $seatData['flight_class_id'],
                            'seat_code' => $seatData['seat_code'],
                            'additional_price_usd' => $seatData['additional_price_usd'] ?? 0,
                            'status' => 'booked'
                        ]);
                    }
                }

                Passenger::create([
                    'booking_id' => $parentBooking->id,
                    'assigned_seats' => $outboundAssignedSeats,
                    'title' => $p['title'],
                    'first_name' => $p['first_name'],
                    'last_name' => $p['last_name'],
                    'date_of_birth' => $p['date_of_birth'],
                    'nationality' => $p['nationality'],
                    'passport_number' => $p['passport_number'] ?? null,
                    'extra_baggage_kg' => $p['extra_baggage_kg'] ?? 0,
                    'baggage_fee_usd' => $baggageFee,
                ]);

                if ($childBooking && isset($p['return_seats'])) {
                    $totalChildPrice += $childBooking->flight->starting_price;

                    foreach ($p['return_seats'] as $retSeatSelection) {
                        $segId = $retSeatSelection['segment_id'];
                        $seatData = $retSeatSelection['seat'];

                        $totalChildPrice += ($seatData['additional_price_usd'] ?? 0);
                        $returnAssignedSeats['segment_' . $segId] = $seatData['seat_code'];

                        Seat::create([
                            'flight_segment_id' => $segId,
                            'flight_class_id' => $seatData['flight_class_id'],
                            'seat_code' => $seatData['seat_code'],
                            'additional_price_usd' => $seatData['additional_price_usd'] ?? 0,
                            'status' => 'booked'
                        ]);
                    }

                    Passenger::create([
                        'booking_id' => $childBooking->id,
                        'assigned_seats' => $returnAssignedSeats,
                        'title' => $p['title'],
                        'first_name' => $p['first_name'],
                        'last_name' => $p['last_name'],
                        'date_of_birth' => $p['date_of_birth'],
                        'nationality' => $p['nationality'],
                        'passport_number' => $p['passport_number'] ?? null,
                        'extra_baggage_kg' => 0,
                        'baggage_fee_usd' => 0,
                    ]);
                }
            }

            $grandTotal = $totalParentPrice + $totalChildPrice;
            $parentBooking->update(['total_amount_usd' => $totalParentPrice, 'status' => 'pending']);
            if ($childBooking) $childBooking->update(['total_amount_usd' => $totalChildPrice, 'status' => 'pending']);

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

            // Hapus session sementara karena data penumpang sudah tersimpan ke tabel passengers
            session()->forget('aero_pax_' . $parentBooking->id);

            // 👇 GANTI RETURN INERTIA MENJADI REDIRECT AGAR MASUK KE GET REQUEST (Aman saat Refresh)
            return redirect()->route('bookings.payment', ['booking' => $parentBooking->id]);
        });
    }

    // 4.5 CONTINUE PAYMENT
    public function continuePayment(Booking $booking)
    {
        if ($booking->status !== 'pending') {
            return redirect()->route('bookings.history')->with('error', 'Booking ini sudah tidak dapat dibayar.');
        }

        $booking->load(['flight.segments.airlineData', 'passengers']);
        $childBooking = Booking::with(['flight.segments.airlineData'])->where('parent_booking_id', $booking->id)->first();
        $grandTotal = $booking->total_amount_usd + ($childBooking ? $childBooking->total_amount_usd : 0);

        Stripe::setApiKey(env('STRIPE_SECRET'));

        try {
            $paymentIntent = \Stripe\PaymentIntent::retrieve($booking->stripe_payment_id);
            $clientSecret = $paymentIntent->client_secret;
        } catch (\Exception $e) {
            $paymentIntent = \Stripe\PaymentIntent::create([
                'amount' => $grandTotal * 100,
                'currency' => 'usd',
                'description' => 'AeroFlight Booking - ' . $booking->flight->origin_airport . ' to ' . $booking->flight->destination_airport . ($childBooking ? ' (Round Trip)' : ''),
                'metadata' => ['parent_booking_id' => $booking->id],
                'receipt_email' => Auth::user()->email,
            ]);

            $booking->update(['stripe_payment_id' => $paymentIntent->id]);
            if ($childBooking) $childBooking->update(['stripe_payment_id' => $paymentIntent->id]);
            $clientSecret = $paymentIntent->client_secret;
        }

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

        // Pastikan load relasi segments
        $parentBooking = Booking::with(['flight.segments.aircraft', 'flight.segments.airlineData', 'passengers'])->find($paymentIntent->metadata->parent_booking_id);
        $childBooking = Booking::with(['flight.segments.aircraft', 'flight.segments.airlineData', 'passengers'])->where('parent_booking_id', $parentBooking->id)->first();

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
            $qrParent = 'AERO-' . $pnrParent . '-' . Str::random(10);

            $parentBooking->update(['status' => 'paid', 'pnr_code' => $pnrParent, 'qr_token' => $qrParent]);
            $parentBooking->transactions()->create(['type' => 'payment', 'amount' => $parentBooking->total_amount_usd, 'description' => 'Stripe Payment (Outbound)']);

            if ($childBooking) {
                $pnrChild = strtoupper(Str::random(6));
                $qrChild = 'AERO-' . $pnrChild . '-' . Str::random(10);

                $childBooking->update(['status' => 'paid', 'pnr_code' => $pnrChild, 'qr_token' => $qrChild]);
                $childBooking->transactions()->create(['type' => 'payment', 'amount' => $childBooking->total_amount_usd, 'description' => 'Stripe Payment (Return)']);
            }

            // PDF dikirim secara queued/background pada implementasi nyata, tapi di sini kita proses langsung
            $pdf = Pdf::setOption(['isRemoteEnabled' => true])->loadView('emails.ticket', [
                'booking' => $parentBooking,
                'child_booking' => $childBooking
            ]);
            Mail::to($request->user()->email)->send(new BookingConfirmed($parentBooking, $childBooking, $pdf->output()));
        }

        return Inertia::render('Checkout/Success', [
            'booking' => $parentBooking,
            'child_booking' => $childBooking
        ]);
    }
}
