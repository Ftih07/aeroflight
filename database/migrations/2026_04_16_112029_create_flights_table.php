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
            // Provider dan rute keseluruhan
            $table->enum('provider', ['internal', 'duffel'])->default('internal');
            $table->string('provider_flight_id')->nullable();
            $table->string('origin_airport');
            $table->string('destination_airport');

            // Waktu keseluruhan dari bandara asal hingga tiba di tujuan akhir
            $table->dateTime('departure_at');
            $table->dateTime('arrival_at');

            // Total stop (0 = direct, 1 = 1 stop transit)
            $table->integer('stop_count')->default(0);

            // Policy Umum
            $table->boolean('is_refundable')->default(false);
            $table->decimal('refund_fee_usd', 10, 2)->nullable();

            $table->text('policy_notes')->nullable();

            $table->timestamps();
        });

        Schema::create('flight_segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flight_id')->constrained()->cascadeOnDelete();
            $table->foreignId('aircraft_id')->nullable()->constrained('aircrafts')->onDelete('set null');

            $table->string('airline_code');
            $table->string('flight_number'); // Contoh: GA123 untuk segmen pertama, SQ456 untuk segmen kedua
            $table->string('origin_airport');
            $table->string('destination_airport');

            $table->dateTime('departure_at');
            $table->dateTime('arrival_at');
            $table->integer('segment_order')->default(1); // 1, 2, 3...

            $table->timestamps();
        });

        Schema::create('flight_classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flight_segment_id')->constrained()->cascadeOnDelete();

            $table->enum('class_type', ['economy', 'business', 'first_class'])->default('economy');
            $table->decimal('base_price_usd', 10, 2);

            // Fasilitas & Bagasi per kelas
            $table->json('facilities')->nullable();
            $table->integer('cabin_baggage_kg')->default(7);
            $table->integer('free_baggage_kg')->default(20);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flights');
    }
};
