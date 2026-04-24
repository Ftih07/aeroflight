<?php

use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

// --- CONTROLLER IMPORTS ---
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\FlightController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\BookingController;

require __DIR__ . '/settings.php';

// --- PUBLIC ROUTES (Bisa diakses tanpa login) ---
Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::get('/flights', [FlightController::class, 'search'])->name('flights.search');

Route::get('/auth/google', [GoogleController::class, 'redirect'])->name('google.login');
Route::get('/auth/google/callback', [GoogleController::class, 'callback']);


// --- PROTECTED ROUTES (Hanya bisa diakses kalau sudah login) ---
Route::middleware(['auth', 'verified'])->group(function () {

    // Dashboard Bawaan
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    // Select + Booking Seat
    Route::post('/bookings/init', [CheckoutController::class, 'initBooking'])->name('bookings.init');
    Route::get('/bookings/{booking_session}/passengers', [CheckoutController::class, 'passengerForm'])->name('bookings.passengers');
    Route::post('/bookings/{booking_session}/seats', [CheckoutController::class, 'selectSeat'])->name('bookings.seats');
    Route::post('/bookings/{booking_session}/checkout', [CheckoutController::class, 'checkout'])->name('bookings.checkout');
    Route::get('/bookings/{booking}/payment', [CheckoutController::class, 'continuePayment'])->name('bookings.payment');
    
    // Checkout
    Route::get('/checkout/success', [CheckoutController::class, 'success'])->name('checkout.success');

    // Area Manajemen Tiket User (History)
    Route::get('/my-bookings', [BookingController::class, 'history'])->name('bookings.history');
    Route::get('/bookings/{booking}/ticket', [BookingController::class, 'downloadTicket'])->name('tickets.download');
    Route::post('/bookings/{booking}/refund', [BookingController::class, 'requestRefund'])->name('bookings.refund');
});
