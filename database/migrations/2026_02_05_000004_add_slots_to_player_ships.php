<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add weapon_slots and utility_slots to player_ships table.
     *
     * These are copied from the Ship blueprint when the ship is created,
     * allowing individual ships to have their own slot counts (which could
     * potentially be modified by upgrades or variations).
     */
    public function up(): void
    {
        Schema::table('player_ships', function (Blueprint $table) {
            $table->integer('weapon_slots')->default(2)->after('sensors');
            $table->integer('utility_slots')->default(2)->after('weapon_slots');
            $table->integer('shield_strength')->default(50)->after('utility_slots');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('player_ships', function (Blueprint $table) {
            $table->dropColumn(['weapon_slots', 'utility_slots', 'shield_strength']);
        });
    }
};
