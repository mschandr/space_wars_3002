<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('trading_hub_ships', function (Blueprint $table) {
            // Add galaxy_id foreign key for easier querying
            $table->foreignId('galaxy_id')->nullable()->after('trading_hub_id')->constrained()->onDelete('cascade');
            $table->index('galaxy_id');
        });

        // Populate galaxy_id from trading_hub -> point_of_interest -> galaxy
        DB::statement('
            UPDATE trading_hub_ships
            SET galaxy_id = (
                SELECT poi.galaxy_id
                FROM trading_hubs th
                JOIN points_of_interest poi ON th.poi_id = poi.id
                WHERE th.id = trading_hub_ships.trading_hub_id
            )
        ');

        // Make galaxy_id non-nullable after populating
        Schema::table('trading_hub_ships', function (Blueprint $table) {
            $table->foreignId('galaxy_id')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trading_hub_ships', function (Blueprint $table) {
            $table->dropForeign(['galaxy_id']);
            $table->dropIndex(['galaxy_id']);
            $table->dropColumn('galaxy_id');
        });
    }
};
