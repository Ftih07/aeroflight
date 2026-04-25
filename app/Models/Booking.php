<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;
use App\Mail\BookingStatusUpdated;
use Illuminate\Support\Str; 

class Booking extends Model
{
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'flight_id',
        'parent_booking_id',
        'rescheduled_from_id',
        'pnr_code',
        'provider_pnr',
        'qr_token',
        'total_amount_usd',
        'promo_code',
        'discount_amount_usd',
        'insurance_fee_usd',
        'points_used',
        'points_earned',
        'status',
        'stripe_session_id',
        'stripe_payment_id'
    ];

    public function parentBooking()
    {
        return $this->belongsTo(Booking::class, 'parent_booking_id');
    }

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
        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });

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
