<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabel Master Promo
        Schema::create('promos', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->enum('discount_type', ['percentage', 'fixed']);
            $table->decimal('discount_value', 10, 2);
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->integer('quota')->nullable(); // Kuota penggunaan, null = unlimited
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Tabel Master Asuransi (Bisa dipilih saat checkout)
        Schema::create('insurances', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g., "Travel Protection Basic", "Comprehensive Delay"
            $table->text('description')->nullable();
            $table->decimal('price_usd', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('bookings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('parent_booking_id')->nullable()->constrained('bookings')->onDelete('set null'); // Untuk Round-trip
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('flight_id')->constrained()->cascadeOnDelete();
            $table->string('pnr_code')->unique()->nullable();
            $table->string('provider_pnr')->nullable();
            $table->string('qr_token')->unique()->nullable(); // Token khusus untuk URL scan QR

            // --- Payment & Add-ons ---
            $table->decimal('total_amount_usd', 10, 2);
            $table->decimal('final_amount_usd', 10, 2)->default(0);
            
            // 1. Relasi ke Master Data (Bisa di-null kalau user nggak pakai)
            $table->foreignId('promo_id')->nullable()->constrained('promos')->onDelete('set null');
            $table->foreignId('insurance_id')->nullable()->constrained('insurances')->onDelete('set null');

            // 2. Snapshot Nominal (Wajib ada biar history transaksi nggak berubah kalau master datanya diubah)
            $table->decimal('discount_amount_usd', 10, 2)->default(0);
            $table->decimal('insurance_fee_usd', 10, 2)->default(0);

            // --- Loyalty ---
            $table->integer('points_used')->default(0);
            $table->integer('points_earned')->default(0);

            // --- Status & Stripe ---
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
