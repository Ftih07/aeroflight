<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FlightClass extends Model
{
    protected $fillable = [
        'flight_segment_id',
        'class_type',
        'base_price_usd',
        'facilities',
        'cabin_baggage_kg',
        'free_baggage_kg',
    ];

    protected function casts(): array
    {
        return [
            'facilities' => 'array',
        ];
    }

    public function segment()
    {
        return $this->belongsTo(FlightSegment::class, 'flight_segment_id');
    }

    public function seats()
    {
        return $this->hasMany(Seat::class);
    }
}
