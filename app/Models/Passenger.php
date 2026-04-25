<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Passenger extends Model
{
    protected $fillable = [
        'booking_id',
        'assigned_seats', // Menggantikan 'seat_code', format JSON: {"segment_1": "12A", "segment_2": "14B"}
        'title',
        'first_name',
        'last_name',
        'date_of_birth',
        'nationality',
        'passport_number',
        'extra_baggage_kg',
        'baggage_fee_usd'
    ];

    protected function casts(): array
    {
        return [
            'assigned_seats' => 'array',
            'date_of_birth' => 'date',
        ];
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }
}
