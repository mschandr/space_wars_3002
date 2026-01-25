<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add canonical coordinate columns to warp_gates for O(1) duplicate detection.
     *
     * Coordinates are stored in canonical order: the endpoint with lower X comes first.
     * If X values are equal, the endpoint with lower Y comes first.
     *
     * Example: Gate between (500, 315) and (250, 333)
     * - Canonical order: (250, 333) -> (500, 315)
     * - source_x=250, source_y=333, dest_x=500, dest_y=315
     */
    public function up(): void
    {
        Schema::table('warp_gates', function (Blueprint $table) {
            // Canonical source coordinates (always lower X, or lower Y if X equal)
            $table->integer('source_x')->nullable()->after('destination_poi_id');
            $table->integer('source_y')->nullable()->after('source_x');

            // Canonical destination coordinates
            $table->integer('dest_x')->nullable()->after('source_y');
            $table->integer('dest_y')->nullable()->after('dest_x');

            // Unique constraint for fast duplicate detection
            // This allows bulk inserts with INSERT IGNORE
            $table->unique(
                ['galaxy_id', 'source_x', 'source_y', 'dest_x', 'dest_y'],
                'warp_gates_canonical_coords_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('warp_gates', function (Blueprint $table) {
            $table->dropUnique('warp_gates_canonical_coords_unique');
            $table->dropColumn(['source_x', 'source_y', 'dest_x', 'dest_y']);
        });
    }
};
