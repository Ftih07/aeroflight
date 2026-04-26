<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Flight;
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
        $bookings = Booking::with([
            'flight',
            'flight.segments.aircraft',
            'flight.segments.airlineData',
            'flight.segments.classes',
            'transactions',
            'passengers',
            'promo',      // Pastikan ini ada
            'insurance'   // Pastikan ini ada
        ])
            ->withCount('passengers')
            ->where('user_id', $userId)
            ->whereNull('parent_booking_id') // Hanya ambil Outbound
            ->orderBy('created_at', 'desc')
            ->get();

        // 2. Ambil SEMUA child bookings yang nyambung ke parent di atas
        $parentIds = $bookings->pluck('id');
        $childBookings = Booking::with([
            'flight',
            'flight.segments.aircraft',
            'flight.segments.airlineData',
            'flight.segments.classes',
            'passengers',
            // Child booking nggak nyimpen promo/insurance, tapi nggak apa-apa diload untuk konsistensi
        ])
            ->whereIn('parent_booking_id', $parentIds)
            ->get()
            ->keyBy('parent_booking_id');

        // 3. Looping dan gabungin parent + child
        $bookings->map(function ($booking) use ($childBookings) {
            // Append starting_price agar terbaca di Vue
            if ($booking->flight) {
                $booking->flight->append('starting_price');
            }

            if (isset($childBookings[$booking->id])) {
                $child = $childBookings[$booking->id];
                if ($child->flight) {
                    $child->flight->append('starting_price');
                }
                $booking->return_booking = $child;
            } else {
                $booking->return_booking = null;
            }

            return $booking;
        });

        return Inertia::render('Bookings/History', ['bookings' => $bookings]);
    }

    public function downloadTicket(Booking $booking)
    {
        // Load relasi promo & insurance agar bisa dibaca di PDF
        $booking->load([
            'flight.segments.aircraft',
            'flight.segments.classes',
            'flight.segments.airlineData',
            'passengers',
            'promo',       // Tambahan
            'insurance'    // Tambahan
        ]);

        $childBooking = Booking::with(['flight.segments.aircraft', 'flight.segments.classes', 'flight.segments.airlineData', 'passengers'])
            ->where('parent_booking_id', $booking->id)
            ->first();

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

        $booking->flight->origin_city = $airportsMap[$booking->flight->origin_airport] ?? 'CITY N/A';
        $booking->flight->destination_city = $airportsMap[$booking->flight->destination_airport] ?? 'CITY N/A';

        if ($childBooking) {
            $childBooking->flight->origin_city = $airportsMap[$childBooking->flight->origin_airport] ?? 'CITY N/A';
            $childBooking->flight->destination_city = $airportsMap[$childBooking->flight->destination_airport] ?? 'CITY N/A';
        }

        $pdf = Pdf::setOption(['isRemoteEnabled' => true])->loadView('emails.ticket', [
            'booking' => $booking,
            'child_booking' => $childBooking
        ]);

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
