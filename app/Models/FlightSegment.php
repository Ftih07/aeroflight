<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class FlightSegment extends Model
{
    protected $fillable = [
        'flight_id',
        'aircraft_id',
        'airline_code',
        'flight_number',
        'origin_airport',
        'destination_airport',
        'departure_at',
        'arrival_at',
        'segment_order',
    ];

    protected function casts(): array
    {
        return [
            'departure_at' => 'datetime',
            'arrival_at' => 'datetime',
        ];
    }

    public function flight()
    {
        return $this->belongsTo(Flight::class);
    }

    public function aircraft()
    {
        return $this->belongsTo(Aircraft::class);
    }

    public function classes()
    {
        return $this->hasMany(FlightClass::class);
    }

    public function seats()
    {
        return $this->hasMany(Seat::class);
    }

    // ==========================================
    // FUNGSI AIRLINE PINDAH KESINI
    // ==========================================

    public function airlineData()
    {
        return $this->belongsTo(Airline::class, 'airline_code', 'code');
    }

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
