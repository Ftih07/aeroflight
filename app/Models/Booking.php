<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;
use App\Mail\BookingStatusUpdated;

class Booking extends Model
{
    protected $fillable = [
        'user_id',
        'flight_id',
        'parent_booking_id', // Baru (Untuk Reschedule)
        'pnr_code',
        'provider_pnr', // Baru
        'total_amount_usd',
        'status',
        'stripe_payment_id'
    ];

    // Relasi ke booking lama jika ini adalah tiket hasil reschedule
    public function parentBooking()
    {
        return $this->belongsTo(Booking::class, 'parent_booking_id');
    }

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

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    protected static function booted()
    {
        static::updated(function ($booking) {
            if ($booking->wasChanged('status')) {
                if ($booking->status !== 'pending') {
                    $email = $booking->user->email ?? 'naufal@test.com';
                    Mail::to($email)->send(new BookingStatusUpdated($booking));
                }
            }
        });
    }
}
