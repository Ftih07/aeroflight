<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Aircraft extends Model
{
    protected $table = 'aircrafts';
    
    protected $fillable = [
        'model_name',
        'seat_layout'
    ];

    protected function casts(): array
    {
        return [
            'seat_layout' => 'array',
        ];
    }

    public function flights()
    {
        return $this->hasMany(Flight::class);
    }
}
