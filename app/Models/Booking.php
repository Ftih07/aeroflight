<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    protected $fillable = [
        'user_id',
        'flight_id',
        'pnr_code',
        'total_amount_usd',
        'status',
        'stripe_payment_id'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function flight()
    {
        return $this->belongsTo(Flight::class);
    }

    public function passengers()
    {
        return $this->hasMany(Passenger::class);
    }
}
