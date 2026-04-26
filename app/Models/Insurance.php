<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Insurance extends Model
{
    protected $fillable = [
        'name',
        'description',
        'price_usd',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'price_usd' => 'decimal:2',
        ];
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }
}
