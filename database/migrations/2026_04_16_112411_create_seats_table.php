<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flight_segment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('flight_class_id')->constrained()->cascadeOnDelete();

            $table->string('seat_code'); // Contoh: 12A
            $table->decimal('additional_price_usd', 10, 2)->default(0); // Misal untuk kursi dengan legroom ekstra

            // Opsional: Kolom status untuk mempermudah pengecekan tanpa harus join ke tabel bookings/passengers
            $table->enum('status', ['available', 'booked', 'blocked'])->default('available');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seats');
    }
};
