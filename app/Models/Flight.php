<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Flight extends Model
{
    protected $fillable = [
        'provider',
        'provider_flight_id',
        'origin_airport',
        'destination_airport',
        'stop_count',
        'departure_at',
        'arrival_at',
        'is_refundable',
        'refund_fee_usd',       // Tambahan
        'policy_notes',         // Tambahan
    ];

    protected function casts(): array
    {
        return [
            'departure_at' => 'datetime',
            'arrival_at' => 'datetime',
            'is_refundable' => 'boolean',
            'refund_fee_usd' => 'decimal:2',
        ];
    }

    public function segments()
    {
        return $this->hasMany(FlightSegment::class)->orderBy('segment_order');
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    // Accessor untuk mendapatkan total harga termurah (Economy) dari seluruh segmen
    public function getStartingPriceAttribute()
    {
        return $this->segments->sum(function ($segment) {
            return $segment->classes->where('class_type', 'economy')->min('base_price_usd') ?? 0;
        });
    }
}
