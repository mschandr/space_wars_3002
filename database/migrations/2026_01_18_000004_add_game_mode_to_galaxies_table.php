<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add a game_mode column and an owner_user_id foreign key to the galaxies table.
     *
     * Adds a string column `game_mode` (length 20) with default `'multiplayer'` and documents possible values: `multiplayer` (real players only), `single_player` (single player with NPCs), and `mixed` (both real players and NPCs). Adds a nullable `owner_user_id` foreign key referencing the `users` table; when the referenced user is deleted the `owner_user_id` is set to null.
     */
    public function up(): void
    {
        Schema::table('galaxies', function (Blueprint $table) {
            $table->string('game_mode', 20)->default('multiplayer')->after('is_public');
            // multiplayer - Real players only
            // single_player - Single player with NPCs
            // mixed - Both real players and NPCs

            $table->foreignId('owner_user_id')->nullable()->after('game_mode')->constrained('users')->onDelete('set null');
            // For single_player mode, tracks who owns this private galaxy
        });
    }

    /**
     * Remove the `game_mode` column and the `owner_user_id` foreign key and column from the `galaxies` table.
     *
     * The foreign key constraint on `owner_user_id` is dropped before the columns are removed.
     */
    public function down(): void
    {
        Schema::table('galaxies', function (Blueprint $table) {
            $table->dropForeign(['owner_user_id']);
            $table->dropColumn(['game_mode', 'owner_user_id']);
        });
    }
};