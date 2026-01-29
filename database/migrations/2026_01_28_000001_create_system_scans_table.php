<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * System scans track what a player has learned about each system.
     * Scan data is cached per player per POI, and updated when they
     * re-scan with higher sensor levels.
     */
    public function up(): void
    {
        Schema::create('system_scans', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // References
            $table->foreignId('player_id')
                ->constrained('players')
                ->cascadeOnDelete();

            $table->foreignId('poi_id')
                ->constrained('points_of_interest')
                ->cascadeOnDelete();

            // Scan level achieved (1-9, matches sensor level)
            $table->unsignedTinyInteger('scan_level');

            // Cached scan results organized by level
            // Structure: { "1": {...}, "2": {...}, ... }
            $table->json('scan_data');

            // When the scan was performed/updated
            $table->timestamp('scanned_at')->nullable();
            $table->timestamps();

            // Unique constraint: one scan record per player per system
            $table->unique(['player_id', 'poi_id']);

            // Indexes for common queries
            $table->index(['player_id', 'scan_level']);
            $table->index(['poi_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_scans');
    }
};
