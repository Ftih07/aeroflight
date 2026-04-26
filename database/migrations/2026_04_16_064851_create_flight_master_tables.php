<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // 1. Tabel Airlines
        Schema::create('airlines', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('logo_path')->nullable();
            $table->text('description')->nullable();
            $table->year('founded_year')->nullable();
            $table->string('headquarters')->nullable();
            $table->timestamps();
        });

        // 2. Tabel Aircrafts
        Schema::create('aircrafts', function (Blueprint $table) {
            $table->id();
            $table->string('model_name');
            $table->string('manufacturer')->nullable();
            $table->text('description')->nullable();
            $table->integer('max_range_km')->nullable();
            $table->integer('cruising_speed_kmh')->nullable();
            $table->string('engine_type')->nullable();
            $table->json('seat_layout');
            $table->timestamps();
        });

        // 3. PIVOT TABLE: Penghubung Airlines & Aircrafts (Many-to-Many)
        Schema::create('aircraft_airline', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aircraft_id')->constrained('aircrafts')->onDelete('cascade');
            $table->foreignId('airline_id')->constrained('airlines')->onDelete('cascade');
            $table->timestamps();
        });

        // 4. Tabel Baggage
        Schema::create('baggage_addons', function (Blueprint $table) {
            $table->id();
            $table->string('airline_code');
            // Menambahkan class_type agar bagasi tambahan bisa di-restrict/diberi harga beda per kelas
            $table->enum('class_type', ['economy', 'business', 'first_class'])->nullable();
            $table->integer('weight_kg');
            $table->decimal('price_usd', 10, 2);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('baggage_addons');
        Schema::dropIfExists('aircraft_airline'); // Drop pivot table dulu
        Schema::dropIfExists('aircrafts');
        Schema::dropIfExists('airlines'); // Tambahan: Biar rollback-nya bersih
    }
};
