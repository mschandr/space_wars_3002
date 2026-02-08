<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Ship variations allow individual ships of the same class to have slightly different
     * characteristics, making each ship unique - just like real-world ships.
     *
     * Modifiers are stored as percentages (e.g., 1.05 = 5% faster, 0.95 = 5% slower)
     */
    public function up(): void
    {
        Schema::table('player_ships', function (Blueprint $table) {
            // Variation modifiers (multipliers, 1.0 = baseline)
            $table->decimal('fuel_regen_modifier', 4, 2)->default(1.00)->after('warp_drive');
            $table->decimal('fuel_consumption_modifier', 4, 2)->default(1.00)->after('fuel_regen_modifier');
            $table->decimal('speed_modifier', 4, 2)->default(1.00)->after('fuel_consumption_modifier');

            // Hidden cargo hold for smuggling ships (not accessible by pirates)
            $table->integer('hidden_hold_capacity')->default(0)->after('cargo_hold');
            $table->integer('hidden_cargo')->default(0)->after('hidden_hold_capacity');

            // Colonist capacity for colony ships
            $table->integer('colonist_capacity')->default(0)->after('hidden_cargo');
            $table->integer('current_colonists')->default(0)->after('colonist_capacity');

            // Ship variation metadata (stores what modifiers were rolled)
            $table->json('variation_traits')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('player_ships', function (Blueprint $table) {
            $table->dropColumn([
                'fuel_regen_modifier',
                'fuel_consumption_modifier',
                'speed_modifier',
                'hidden_hold_capacity',
                'hidden_cargo',
                'colonist_capacity',
                'current_colonists',
                'variation_traits',
            ]);
        });
    }
};
