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
        Schema::create('precursor_ships', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('galaxy_id')->constrained('galaxies')->onDelete('cascade');

            // Hidden in interstellar space (NOT at a POI)
            $table->unsignedInteger('x')->comment('Random coordinates in interstellar void');
            $table->unsignedInteger('y');

            // Discovery mechanics
            $table->boolean('is_discovered')->default(false);
            $table->foreignId('discovered_by_player_id')->nullable()->constrained('players')->onDelete('set null');
            $table->timestamp('discovered_at')->nullable();

            // Claim mechanics
            $table->foreignId('claimed_by_player_id')->nullable()->constrained('players')->onDelete('set null');
            $table->timestamp('claimed_at')->nullable();

            // Ship stats (godlike)
            $table->unsignedBigInteger('hull')->default(1000000)->comment('100x any player ship');
            $table->unsignedBigInteger('max_hull')->default(1000000);
            $table->unsignedInteger('weapons')->default(10000)->comment('100x best weapons');
            $table->unsignedInteger('sensors')->default(100)->comment('100x sensor range');
            $table->unsignedInteger('speed')->default(10000)->comment('100x fastest ship');
            $table->unsignedInteger('warp_drive')->default(100)->comment('Interstellar flight capable');

            // Pocket dimension storage
            $table->unsignedBigInteger('cargo_capacity')->default(1000000)->comment('Pocket dimension: 1M units');
            $table->unsignedBigInteger('current_cargo')->default(0);

            // Infinite fuel (cosmetic - never depletes)
            $table->unsignedBigInteger('fuel')->default(999999999);
            $table->unsignedBigInteger('max_fuel')->default(999999999);

            // Precursor tech attributes (JSON)
            $table->json('precursor_tech')->nullable()->comment('Jump drive, shield harmonics, etc.');

            // Lore
            $table->text('description')->nullable();
            $table->string('precursor_name')->default('Void Strider')->comment('Original Precursor designation');

            $table->timestamps();

            // Indexes
            $table->index(['galaxy_id', 'x', 'y']);
            $table->index(['is_discovered', 'galaxy_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('precursor_ships');
    }
};
