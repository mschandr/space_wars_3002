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
        Schema::create('colonies', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('player_id')->constrained('players')->onDelete('cascade');
            $table->foreignId('poi_id')->constrained('points_of_interest')->onDelete('cascade');
            $table->string('name');

            // Population
            $table->integer('population')->default(100); // Starting colonists
            $table->decimal('population_growth_rate', 5, 2)->default(0.05); // 5% per cycle
            $table->integer('max_population')->default(10000); // Increases with infrastructure

            // Resources
            $table->integer('food_production')->default(0);
            $table->integer('food_storage')->default(1000);
            $table->integer('mineral_production')->default(0);
            $table->integer('mineral_storage')->default(500);
            $table->integer('credits_per_cycle')->default(0); // ROI from colony

            // Development
            $table->integer('development_level')->default(1); // 1-10 stages
            $table->decimal('habitability_rating', 3, 2); // 0.0-1.0 (calculated from POI)
            $table->string('status')->default('establishing'); // establishing, growing, established, threatened

            // Timestamps
            $table->timestamp('established_at');
            $table->timestamp('last_growth_at')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('player_id');
            $table->index('poi_id');
            $table->unique(['player_id', 'poi_id']); // One colony per POI per player
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('colonies');
    }
};
