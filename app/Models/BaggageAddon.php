<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BaggageAddon extends Model
{
    protected $fillable = [
        'airline_code',
        'weight_kg',
        'price_usd'
    ];
}
