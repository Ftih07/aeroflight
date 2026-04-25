<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Seat extends Model
{
    protected $fillable = [
        'flight_segment_id',
        'flight_class_id',
        'seat_code',
        'additional_price_usd',
        'status', // Tambahan dari migration baru
    ];

    public function segment()
    {
        return $this->belongsTo(FlightSegment::class, 'flight_segment_id');
    }

    public function flightClass()
    {
        return $this->belongsTo(FlightClass::class, 'flight_class_id');
    }
}
