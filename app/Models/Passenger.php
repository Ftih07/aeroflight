<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Passenger extends Model
{
    protected $fillable = [
        'booking_id',
        'seat_code', // Berubah dari seat_id
        'title', // Baru
        'first_name',
        'last_name',
        'date_of_birth', // Baru
        'nationality', // Baru
        'passport_number',
        'extra_baggage_kg', // Baru
        'baggage_fee_usd' // Baru
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }
}
