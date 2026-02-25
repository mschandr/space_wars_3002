<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Ships get their own location in space. On creation, backfill from
     * the owning player's current_poi_id so every existing ship starts
     * wherever its player currently is.
     */
    public function up(): void
    {
        Schema::table('player_ships', function (Blueprint $table) {
            $table->unsignedBigInteger('current_poi_id')->nullable()->after('ship_id');

            $table->foreign('current_poi_id')
                ->references('id')
                ->on('points_of_interest')
                ->nullOnDelete();

            $table->index('current_poi_id');
        });

        // Backfill: set each ship's location to its player's current location
        // Uses subquery syntax compatible with both MySQL and SQLite
        DB::statement('
            UPDATE player_ships
            SET current_poi_id = (
                SELECT p.current_poi_id FROM players p
                WHERE p.id = player_ships.player_id
            )
            WHERE current_poi_id IS NULL
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('player_ships', function (Blueprint $table) {
            $table->dropForeign(['current_poi_id']);
            $table->dropIndex(['current_poi_id']);
            $table->dropColumn('current_poi_id');
        });
    }
};
