<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add indexes to optimize spatial queries for POIs.
 *
 * These indexes improve performance for:
 * - Nearby system searches (NavigationController)
 * - Trading hub discovery (TradingController)
 * - Sensor range scans
 * - Any query using x/y coordinate filters
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('points_of_interest', function (Blueprint $table) {
            // Composite index for galaxy + coordinate range queries
            // This is the most common query pattern for spatial searches
            $table->index(['galaxy_id', 'x', 'y'], 'poi_galaxy_coords_idx');

            // Index for type-filtered spatial queries (e.g., find nearby stars)
            $table->index(['galaxy_id', 'type', 'x', 'y'], 'poi_galaxy_type_coords_idx');

            // Index for inhabited system lookups within coordinate range
            $table->index(['galaxy_id', 'is_inhabited', 'x', 'y'], 'poi_galaxy_inhabited_coords_idx');
        });

        // Add indexes to sectors for coordinate-based lookups
        Schema::table('sectors', function (Blueprint $table) {
            // Composite index for sector bounding box queries
            $table->index(['galaxy_id', 'x_min', 'x_max', 'y_min', 'y_max'], 'sector_galaxy_bounds_idx');
        });

        // Add index to warp_gates for source POI lookups
        Schema::table('warp_gates', function (Blueprint $table) {
            // Composite index for outgoing gates with status filter
            $table->index(['source_poi_id', 'is_hidden', 'status'], 'warpgate_source_visible_idx');
        });

        // Add index to pilot_lane_knowledge for bulk lookups
        Schema::table('pilot_lane_knowledge', function (Blueprint $table) {
            // For efficient player-based gate knowledge queries
            $table->index(['player_id', 'warp_gate_id'], 'lane_knowledge_player_gate_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('points_of_interest', function (Blueprint $table) {
            $table->dropIndex('poi_galaxy_coords_idx');
            $table->dropIndex('poi_galaxy_type_coords_idx');
            $table->dropIndex('poi_galaxy_inhabited_coords_idx');
        });

        Schema::table('sectors', function (Blueprint $table) {
            $table->dropIndex('sector_galaxy_bounds_idx');
        });

        Schema::table('warp_gates', function (Blueprint $table) {
            $table->dropIndex('warpgate_source_visible_idx');
        });

        Schema::table('pilot_lane_knowledge', function (Blueprint $table) {
            $table->dropIndex('lane_knowledge_player_gate_idx');
        });
    }
};
