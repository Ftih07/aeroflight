<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_booking_id')->nullable()->constrained('bookings')->onDelete('set null');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('flight_id')->constrained()->cascadeOnDelete();
            $table->string('pnr_code')->unique()->nullable();
            $table->string('provider_pnr')->nullable();
            $table->decimal('total_amount_usd', 10, 2);
            $table->string('status')->default('pending');
            $table->string('stripe_payment_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
