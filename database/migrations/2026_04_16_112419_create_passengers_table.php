<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('passengers', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('booking_id')->constrained()->cascadeOnDelete();

            // Diubah jadi JSON untuk menyimpan seat per segment.
            // Contoh format: {"segment_1": "12A", "segment_2": "14B"}
            $table->json('assigned_seats')->nullable();

            $table->enum('title', ['Mr', 'Mrs', 'Ms', 'Miss'])->default('Mr');
            $table->string('first_name');
            $table->string('last_name');
            $table->date('date_of_birth')->nullable();
            $table->string('nationality')->default('ID');
            $table->string('passport_number')->nullable();
            $table->integer('extra_baggage_kg')->default(0);
            $table->decimal('baggage_fee_usd', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('passengers');
    }
};
