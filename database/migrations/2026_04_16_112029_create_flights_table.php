<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aircraft_id')->nullable()->constrained('aircrafts')->onDelete('set null');

            $table->string('airline_code');
            $table->enum('provider', ['internal', 'duffel'])->default('internal');
            $table->string('provider_flight_id')->nullable();
            $table->string('flight_number');
            $table->string('origin_airport');
            $table->string('destination_airport');

            // Informasi Perjalanan & Transit
            $table->integer('stop_count')->default(0); // Mempermudah filter (0 = direct, 1 = 1 stop)
            $table->json('transits')->nullable();
            $table->dateTime('departure_at');
            $table->dateTime('arrival_at');

            // Pricing & Policies
            $table->decimal('base_price_usd', 10, 2);
            $table->json('seat_prices')->nullable();
            $table->boolean('is_refundable')->default(false); // Policy
            $table->boolean('is_reschedulable')->default(false); // Policy

            // Fasilitas & Bagasi
            $table->json('facilities')->nullable(); // ['meal' => true, 'wifi' => false]
            $table->integer('cabin_baggage_kg')->default(7); // Bagasi kabin
            $table->integer('free_baggage_kg')->default(20); // Bagasi dalam (checked)

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flights');
    }
};
