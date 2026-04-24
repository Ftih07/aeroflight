<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class Flight extends Model
{
    protected $fillable = [
        'aircraft_id',
        'flight_number',
        'airline_code',
        'provider',
        'provider_flight_id',
        'origin_airport',
        'destination_airport',
        'stop_count', // Baru
        'transits',
        'departure_at',
        'arrival_at',
        'base_price_usd',
        'seat_prices',
        'is_refundable', // Baru
        'is_reschedulable', // Baru
        'facilities', // Baru
        'cabin_baggage_kg', // Baru
        'free_baggage_kg'
    ];

    protected function casts(): array
    {
        return [
            'departure_at' => 'datetime',
            'arrival_at' => 'datetime',
            'transits' => 'array',
            'seat_prices' => 'array',
            'facilities' => 'array', // Jangan lupa di-cast jadi array
            'is_refundable' => 'boolean',
            'is_reschedulable' => 'boolean',
        ];
    }

    public function aircraft()
    {
        return $this->belongsTo(Aircraft::class);
    }

    public function seats()
    {
        return $this->hasMany(Seat::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    // "Soft Relation" ke tabel airlines lokal menggunakan string 'code'
    public function airlineData()
    {
        return $this->belongsTo(Airline::class, 'airline_code', 'code');
    }

    // Accessor canggih: Cek DB lokal dulu, kalau nggak ada baru hit OpenFlights Cache
    protected function airlineInfo(): Attribute
    {
        return Attribute::make(
            get: function () {
                // 1. Cek Database Lokal
                if ($this->airlineData) {
                    return [
                        'name' => $this->airlineData->name,
                        'logo' => $this->airlineData->logo_path,
                        'description' => $this->airlineData->description,
                        'is_detailed' => true
                    ];
                }

                // 2. Fallback ke OpenFlights
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

                return [
                    'name' => $airlinesMap[$this->airline_code] ?? $this->airline_code,
                    'logo' => null,
                    'description' => 'Informasi detail armada belum tersedia untuk maskapai ini.',
                    'is_detailed' => false
                ];
            }
        );
    }
}
