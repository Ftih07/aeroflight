<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('airlines', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); 
            $table->string('name');
            $table->string('logo_path')->nullable();
            $table->text('description')->nullable(); 
            $table->timestamps();
        });

        Schema::create('aircrafts', function (Blueprint $table) {
            $table->id();
            $table->string('model_name');
            $table->string('manufacturer')->nullable();
            $table->text('description')->nullable();
            $table->json('seat_layout');
            $table->timestamps();
        });

        Schema::create('baggage_addons', function (Blueprint $table) {
            $table->id();
            $table->string('airline_code');
            $table->integer('weight_kg');
            $table->decimal('price_usd', 10, 2);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('baggage_addons');
        Schema::dropIfExists('aircrafts');
    }
};
