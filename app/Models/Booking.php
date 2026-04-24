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
        'parent_booking_id', // Untuk Round-trip
        'rescheduled_from_id', // Baru: Khusus untuk tracking riwayat Reschedule
        'pnr_code',
        'provider_pnr',
        'qr_token', // Baru
        'total_amount_usd',
        'promo_code', // Baru
        'discount_amount_usd', // Baru
        'insurance_fee_usd', // Baru
        'points_used', // Baru
        'points_earned', // Baru
        'status',
        'stripe_session_id', // Baru: Untuk continue payment
        'stripe_payment_id'
    ];

    // Relasi tiket Pulang (Child) ke tiket Pergi (Parent) dalam satu Round-trip
    public function parentBooking()
    {
        return $this->belongsTo(Booking::class, 'parent_booking_id');
    }

    // Relasi tiket baru (hasil reschedule) ke tiket lama
    public function rescheduledFrom()
    {
        return $this->belongsTo(Booking::class, 'rescheduled_from_id');
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
                if ($booking->status !== 'pending' && $booking->status !== 'awaiting_payment') {
                    $email = $booking->user->email ?? 'naufal@test.com';
                    Mail::to($email)->send(new BookingStatusUpdated($booking));
                }
            }
        });
    }
}
