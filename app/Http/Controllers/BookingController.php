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

        // 1. Ambil data booking berserta relasinya (TAMBAHIN flight.aircraft dan passengers)
        $bookings = Booking::with(['flight.aircraft', 'transactions', 'passengers'])
            ->withCount('passengers')
            ->where('user_id', $userId)
            ->whereNull('parent_booking_id') // Hanya ambil Outbound
            ->orderBy('created_at', 'desc')
            ->get();

        // 2. Ambil SEMUA child bookings yang nyambung ke parent di atas (TAMBAHIN flight.aircraft)
        $parentIds = $bookings->pluck('id');
        $childBookings = Booking::with(['flight.aircraft', 'passengers'])
            ->whereIn('parent_booking_id', $parentIds)
            ->get()
            ->keyBy('parent_booking_id');

        // 3. Terjemahkan Kode Maskapai pakai Cache
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

        // 4. Looping dan gabungin parent + child
        $bookings->map(function ($booking) use ($airlinesMap, $childBookings) {
            if ($booking->flight) {
                $booking->flight->airline_name = $airlinesMap[$booking->flight->airline_code] ?? $booking->flight->airline_code;
            }

            if (isset($childBookings[$booking->id])) {
                $child = $childBookings[$booking->id];
                if ($child->flight) {
                    $child->flight->airline_name = $airlinesMap[$child->flight->airline_code] ?? $child->flight->airline_code;
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
        $booking->load(['flight.aircraft', 'passengers']);

        // Cari tiket pulang kalau ini adalah tiket berangkat (Parent)
        $childBooking = Booking::with(['flight.aircraft', 'passengers'])
            ->where('parent_booking_id', $booking->id)
            ->first();

        // Ambil Data Maskapai
        $airlinesMap = Cache::get('openflights_airlines', []);
        $booking->flight->airline_name = $airlinesMap[$booking->flight->airline_code] ?? $booking->flight->airline_code;
        if ($childBooking) {
            $childBooking->flight->airline_name = $airlinesMap[$childBooking->flight->airline_code] ?? $childBooking->flight->airline_code;
        }

        // 👇 TAMBAHAN: Ambil Cache Nama Kota Bandara (Sama seperti di Success)
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
        $booking->flight->origin_city = $airportsMap[$booking->flight->origin_airport] ?? 'CITY N/A';
        $booking->flight->destination_city = $airportsMap[$booking->flight->destination_airport] ?? 'CITY N/A';

        if ($childBooking) {
            $childBooking->flight->origin_city = $airportsMap[$childBooking->flight->origin_airport] ?? 'CITY N/A';
            $childBooking->flight->destination_city = $airportsMap[$childBooking->flight->destination_airport] ?? 'CITY N/A';
        }

        // Render PDF
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
