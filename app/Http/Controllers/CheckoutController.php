<?php

namespace App\Http\Controllers;

use App\Models\Flight;
use App\Models\Booking;
use App\Models\Passenger;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Stripe\Stripe;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Mail\BookingConfirmed;
use App\Models\Seat;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

class CheckoutController extends Controller
{
    public function passengerForm(Request $request, $id) // Ubah parameter ke $id
    {
        $request->validate(['selected_seats' => 'required|array|min:1']);

        // Load data penerbangan beserta relasi pesawatnya
        $flight = Flight::with('aircraft')->findOrFail($id);

        // Terjemahkan Kode Maskapai pakai Cache
        $airlinesMap = Cache::remember('openflights_airlines', 86400, function () {
            $response = Http::withoutVerifying()->get('https://raw.githubusercontent.com/jpatokal/openflights/master/data/airlines.dat');
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

        return Inertia::render('Flights/PassengerForm', [
            'flight' => $flight,
            'selectedSeats' => $request->selected_seats
        ]);
    }

    public function checkout(Request $request, Flight $flight)
    {
        // Validasi disesuaikan dengan form baru yang ada Titile, DOB, Nationality, dan Bagasi
        $request->validate([
            'passengers' => 'required|array|min:1',
            'passengers.*.title' => 'required|in:Mr,Mrs,Ms,Miss',
            'passengers.*.first_name' => 'required|string',
            'passengers.*.last_name' => 'required|string',
            'passengers.*.date_of_birth' => 'required|date',
            'passengers.*.nationality' => 'required|string',
            'passengers.*.seat_code' => 'required|string',
            'passengers.*.extra_baggage_kg' => 'nullable|numeric',
            'passengers.*.baggage_fee_usd' => 'nullable|numeric',
            'passengers.*.seat_additional_price_usd' => 'nullable|numeric',
        ]);

        return DB::transaction(function () use ($request, $flight) {
            $totalPrice = 0;

            // Hitung Total Harga (Base Price + Bagasi + Harga Kursi Spesial jika ada)
            foreach ($request->passengers as $p) {
                $baggageFee = $p['baggage_fee_usd'] ?? 0;
                $seatFee = $p['seat_additional_price_usd'] ?? 0;
                $totalPrice += $flight->base_price_usd + $baggageFee + $seatFee;
            }

            // 1. Buat Booking
            $booking = Booking::create([
                'user_id' => Auth::id(),
                'flight_id' => $flight->id,
                'total_amount_usd' => $totalPrice,
                'status' => 'pending',
                // Nanti provider_pnr diisi kalau kita beneran submit order ke API Duffel
            ]);

            // 2. Insert Penumpang & Kunci Kursi
            foreach ($request->passengers as $p) {
                // Simpan Data Penumpang Lengkap
                Passenger::create([
                    'booking_id' => $booking->id,
                    'seat_code' => $p['seat_code'],
                    'title' => $p['title'],
                    'first_name' => $p['first_name'],
                    'last_name' => $p['last_name'],
                    'date_of_birth' => $p['date_of_birth'],
                    'nationality' => $p['nationality'],
                    'passport_number' => $p['passport_number'] ?? null,
                    'extra_baggage_kg' => $p['extra_baggage_kg'] ?? 0,
                    'baggage_fee_usd' => $p['baggage_fee_usd'] ?? 0,
                ]);

                Seat::create([
                    'flight_id' => $flight->id,
                    'seat_code' => $p['seat_code'],
                    'class' => $p['seat_class'] ?? 'economy', // Dinamis ambil dari form
                    'additional_price_usd' => $p['seat_additional_price_usd'] ?? 0,
                ]);
            }

            // 3. Proses Stripe (Kodingan lamamu sudah sempurna, tidak perlu diubah)
            Stripe::setApiKey(env('STRIPE_SECRET'));

            $paymentIntent = \Stripe\PaymentIntent::create([
                'amount' => $totalPrice * 100,
                'currency' => 'usd',
                'description' => 'AeroFlight Booking - ' . $flight->origin_airport . ' to ' . $flight->destination_airport,
                'metadata' => ['booking_id' => $booking->id],
                'receipt_email' => Auth::user()->email,
            ]);

            $booking->update(['stripe_payment_id' => $paymentIntent->id]);

            $airlinesMap = Cache::remember('openflights_airlines', 86400, function () {
                $response = Http::withoutVerifying()->get('https://raw.githubusercontent.com/jpatokal/openflights/master/data/airlines.dat');
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

            return Inertia::render('Flights/Payment', [
                'flight' => $flight,
                'booking' => $booking->load('passengers'),
                'clientSecret' => $paymentIntent->client_secret,
                'stripeKey' => env('STRIPE_KEY')
            ]);
        });
    }

    public function success(Request $request)
    {
        Stripe::setApiKey(env('STRIPE_SECRET'));
        $paymentIntent = \Stripe\PaymentIntent::retrieve($request->payment_intent);

        if ($paymentIntent->status !== 'succeeded') {
            return redirect()->route('home')->with('error', 'Pembayaran gagal atau belum selesai.');
        }

        // LOAD FLIGHT.AIRCRAFT JUGA
        $booking = Booking::with(['flight.aircraft', 'passengers'])->find($paymentIntent->metadata->booking_id);

        if ($booking && $booking->status === 'pending') {
            $booking->update([
                'status' => 'paid',
                'pnr_code' => strtoupper(Str::random(6)),
            ]);

            $booking->transactions()->create([
                'type' => 'payment',
                'amount' => $booking->total_amount_usd,
                'description' => 'Payment received via Stripe Elements'
            ]);

            // Suntik Nama Maskapai buat Email/PDF
            $airlinesMap = Cache::get('openflights_airlines', []);
            $booking->flight->airline_name = $airlinesMap[$booking->flight->airline_code] ?? $booking->flight->airline_code;

            $pdf = Pdf::loadView('emails.ticket', ['booking' => $booking]);
            Mail::to($request->user()->email)->send(new BookingConfirmed($booking, $pdf->output()));
        }

        // Suntik Nama Maskapai buat halaman Vue
        $airlinesMap = Cache::get('openflights_airlines', []);
        $booking->flight->airline_name = $airlinesMap[$booking->flight->airline_code] ?? $booking->flight->airline_code;

        return Inertia::render('Checkout/Success', ['booking' => $booking]);
    }
}
