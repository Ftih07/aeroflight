<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class DuffelService
{
    protected $token;
    protected $baseUrl = 'https://api.duffel.com/air';

    public function __construct()
    {
        $this->token = env('DUFFEL_ACCESS_TOKEN');
    }

    /**
     * Mencari jadwal penerbangan (Duffel menyebutnya "Offer Requests")
     */
    public function searchFlights($origin, $destination, $departureDate)
    {
        // Hit API Duffel
        $response = Http::withToken($this->token)
            ->withHeaders([
                'Duffel-Version' => 'v2', // Versi API Duffel yang diwajibkan
                'Accept' => 'application/json',
            ])
            ->post($this->baseUrl . '/offer_requests', [
                'data' => [
                    'slices' => [
                        [
                            'origin' => $origin,           // cth: 'CGK'
                            'destination' => $destination, // cth: 'DPS'
                            'departure_date' => $departureDate // cth: '2026-05-20'
                        ]
                    ],
                    'passengers' => [
                        ['type' => 'adult'] // Default 1 dewasa untuk pencarian awal
                    ],
                    'cabin_class' => 'economy'
                ]
            ]);

        if ($response->successful()) {
            return $response->json()['data']['offers'];
        }

        // Jika terjadi error dari API
        return null;
    }
}
