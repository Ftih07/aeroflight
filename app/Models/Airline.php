<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Airline extends Model
{
    protected $fillable = [
        'code',
        'name',
        'logo_path',
        'description',
        'founded_year', // Tambahan
        'headquarters'  // Tambahan
    ];

    public function aircrafts()
    {
        return $this->belongsToMany(Aircraft::class);
    }
}
