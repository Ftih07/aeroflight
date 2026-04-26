<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Promo extends Model
{
    protected $fillable = [
        'code',
        'discount_type',
        'discount_value',
        'valid_from',
        'valid_until',
        'quota',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'valid_from' => 'date',
            'valid_until' => 'date',
            'is_active' => 'boolean',
            'discount_value' => 'decimal:2',
        ];
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }
}
