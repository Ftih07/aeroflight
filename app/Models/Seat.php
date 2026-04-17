<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Seat extends Model
{
    protected $fillable = [
        'flight_id',
        'seat_code',
        'class',
        'is_available',
        'additional_price_usd'
    ];

    public function flight()
    {
        return $this->belongsTo(Flight::class);
    }
}
