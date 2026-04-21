<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Flight extends Model
{
    protected $fillable = [
        'aircraft_id', // Baru
        'flight_number',
        'airline_code',
        'provider', // Baru
        'provider_flight_id', // Baru
        'origin_airport',
        'destination_airport',
        'transits',
        'departure_at',
        'arrival_at',
        'base_price_usd',
        'seat_prices',
        'free_baggage_kg' // Baru
    ];

    protected function casts(): array
    {
        return [
            'departure_at' => 'datetime',
            'arrival_at' => 'datetime',
            'transits' => 'array',       // Bikin otomatis jadi array
            'seat_prices' => 'array',    // Bikin otomatis jadi array
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
}
