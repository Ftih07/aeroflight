<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Aircraft extends Model
{
    protected $table = 'aircrafts';

    protected $fillable = [
        'model_name',
        'manufacturer',       // Tambahan
        'description',        // Tambahan
        'max_range_km',       // Tambahan
        'cruising_speed_kmh', // Tambahan
        'engine_type',        // Tambahan
        'seat_layout'
    ];

    protected function casts(): array
    {
        return [
            'seat_layout' => 'array',
        ];
    }

    public function flightSegments()
    {
        return $this->hasMany(FlightSegment::class);
    }

    public function airlines()
    {
        return $this->belongsToMany(Airline::class);
    }
}
