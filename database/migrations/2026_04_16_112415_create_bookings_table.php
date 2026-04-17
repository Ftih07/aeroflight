<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('flight_id')->constrained()->cascadeOnDelete();
            $table->string('pnr_code')->unique()->nullable(); // 6 digit alfanumerik (keluar setelah bayar)
            $table->decimal('total_amount_usd', 10, 2);
            $table->enum('status', ['pending', 'paid', 'expired', 'cancelled'])->default('pending');
            $table->string('stripe_payment_id')->nullable(); // Disimpan saat webhook Stripe sukses
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
