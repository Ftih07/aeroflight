<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Flight extends Model
{
    protected $fillable = [
        'flight_number',
        'airline_code',
        'origin_airport',
        'destination_airport',
        'departure_at',
        'arrival_at',
        'base_price_usd'
    ];

    public function seats()
    {
        return $this->hasMany(Seat::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }
}
