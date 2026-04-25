<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('parent_booking_id')->nullable()->constrained('bookings')->onDelete('set null'); // Untuk Round-trip
            $table->foreignUuid('rescheduled_from_id')->nullable()->constrained('bookings')->onDelete('set null');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('flight_id')->constrained()->cascadeOnDelete();
            $table->string('pnr_code')->unique()->nullable();
            $table->string('provider_pnr')->nullable();
            $table->string('qr_token')->unique()->nullable(); // Token khusus untuk URL scan QR

            // Payment breakdown
            $table->decimal('total_amount_usd', 10, 2);
            $table->string('promo_code')->nullable();
            $table->decimal('discount_amount_usd', 10, 2)->default(0);
            $table->decimal('insurance_fee_usd', 10, 2)->default(0);

            // Loyalty
            $table->integer('points_used')->default(0);
            $table->integer('points_earned')->default(0);

            // Status & Stripe
            $table->string('status')->default('pending'); // pending, awaiting_payment, confirmed, cancelled
            $table->string('stripe_session_id')->nullable(); // Untuk 'continue pay'
            $table->string('stripe_payment_id')->nullable(); // Payment Intent ID setelah sukses

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
