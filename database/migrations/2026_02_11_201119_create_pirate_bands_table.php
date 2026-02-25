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
        Schema::create('pirate_bands', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('galaxy_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sector_id')->constrained()->cascadeOnDelete();
            $table->foreignId('home_base_poi_id')->constrained('points_of_interest')->cascadeOnDelete();
            $table->foreignId('captain_id')->constrained('pirate_captains')->cascadeOnDelete();
            $table->tinyInteger('fleet_size')->default(1);
            $table->tinyInteger('difficulty_tier')->default(1);
            $table->boolean('is_active')->default(true);
            $table->foreignId('current_poi_id')->nullable()->constrained('points_of_interest')->nullOnDelete();
            $table->timestamp('last_moved_at')->nullable();
            $table->timestamp('last_encounter_at')->nullable();
            $table->float('roaming_radius_ly')->nullable();
            $table->timestamps();

            $table->index(['sector_id', 'is_active']);
            $table->index(['galaxy_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pirate_bands');
    }
};
