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
        Schema::create('colony_missions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('colony_id')->constrained('colonies')->onDelete('cascade');
            $table->foreignId('player_ship_id')->constrained('player_ships')->onDelete('cascade');
            $table->foreignId('destination_poi_id')->constrained('points_of_interest');
            $table->string('mission_type'); // colonize, trade_route, defend, explore

            // Mission details
            $table->integer('colonists_aboard')->default(0); // For colonization missions
            $table->integer('cargo_capacity_used')->default(0);
            $table->json('cargo_manifest')->nullable(); // What the ship is carrying

            // Status
            $table->string('status')->default('preparing'); // preparing, in_transit, arrived, completed, failed
            $table->integer('turns_remaining')->nullable(); // Travel time

            // Timestamps
            $table->timestamp('launched_at')->nullable();
            $table->timestamp('arrival_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('colony_id');
            $table->index('player_ship_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('colony_missions');
    }
};
