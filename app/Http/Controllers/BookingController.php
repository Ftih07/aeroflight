<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class BookingController extends Controller
{
    public function history(Request $request)
    {
        $userId = Auth::id();

        // 1. Ambil data booking berserta relasinya
        $bookings = Booking::with(['flight', 'transactions'])
            ->withCount('passengers') // <-- TAMBAHAN: Ubah 'passengers' sesuai nama relasi kamu
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();

        // 2. Terjemahkan Kode Maskapai pakai Cache (seperti di passengerForm)
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

        // 3. Looping semua booking untuk nambahin properti airline_name ke masing-masing flight
        $bookings->map(function ($booking) use ($airlinesMap) {
            if ($booking->flight) {
                $booking->flight->airline_name = $airlinesMap[$booking->flight->airline_code] ?? $booking->flight->airline_code;
            }
            return $booking;
        });

        return Inertia::render('Bookings/History', ['bookings' => $bookings]);
    }

    public function downloadTicket(Booking $booking)
    {
        $booking->load(['flight.aircraft', 'passengers']);

        $airlinesMap = Cache::get('openflights_airlines', []);
        $booking->flight->airline_name = $airlinesMap[$booking->flight->airline_code] ?? $booking->flight->airline_code;

        $pdf = Pdf::loadView('emails.ticket', ['booking' => $booking]);
        return $pdf->download('Ticket-' . $booking->pnr_code . '.pdf');
    }

    public function requestRefund(Booking $booking)
    {
        if ($booking->status === 'paid') {
            $booking->update(['status' => 'refund_requested']);
        }
        return back()->with('message', 'Refund request submitted successfully.');
    }
}
