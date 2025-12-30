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
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('component'); // max_fuel, max_hull, weapons, cargo_hold, sensors, warp_drive
            $table->text('description');
            $table->integer('additional_levels')->default(10); // How many levels beyond max
            $table->decimal('price', 12, 2);
            $table->string('rarity')->default('rare'); // rare, epic, legendary
            $table->json('requirements')->nullable(); // Level, credits, other plans
            $table->timestamps();

            $table->index('component');
            $table->index('rarity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
