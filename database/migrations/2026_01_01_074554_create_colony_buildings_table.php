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
        Schema::create('colony_buildings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('colony_id')->constrained('colonies')->onDelete('cascade');
            $table->string('building_type'); // shipyard, orbital_defense, trade_station, mining_facility, hydroponics, hab_module

            // Building stats
            $table->integer('level')->default(1); // 1-5 levels per building
            $table->string('status')->default('constructing'); // constructing, operational, damaged, destroyed

            // Construction
            $table->integer('construction_progress')->default(0); // 0-100%
            $table->integer('construction_cost_credits')->default(0);
            $table->integer('construction_cost_minerals')->default(0);
            $table->integer('construction_cost_population')->default(0); // Workers needed
            $table->timestamp('construction_started_at')->nullable();
            $table->timestamp('construction_completed_at')->nullable();

            // Production/Effects (JSON for flexibility)
            $table->json('effects')->nullable(); // e.g., {"food_production": 50, "mineral_production": 30}

            $table->timestamps();

            // Indexes
            $table->index('colony_id');
            $table->index(['colony_id', 'building_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('colony_buildings');
    }
};
