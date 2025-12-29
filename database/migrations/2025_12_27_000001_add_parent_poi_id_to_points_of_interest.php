<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add parent-child relationships to Points of Interest.
     * This enables hierarchical structures:
     * - Stars can have planets as children
     * - Planets can have moons as children
     * - NULL parent_poi_id means universe-level POI (star, nebula, black hole, etc.)
     */
    public function up(): void
    {
        Schema::table('points_of_interest', function (Blueprint $table) {
            $table->foreignId('parent_poi_id')
                ->nullable()
                ->after('galaxy_id')
                ->constrained('points_of_interest')
                ->nullOnDelete()
                ->comment('Parent POI (star for planet, planet for moon)');

            $table->unsignedSmallInteger('orbital_index')
                ->nullable()
                ->after('parent_poi_id')
                ->comment('Position in orbital sequence (1=innermost, higher=outer)');

            $table->index(['parent_poi_id', 'orbital_index']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('points_of_interest', function (Blueprint $table) {
            $table->dropForeign(['parent_poi_id']);
            $table->dropIndex(['parent_poi_id', 'orbital_index']);
            $table->dropColumn(['parent_poi_id', 'orbital_index']);
        });
    }
};
