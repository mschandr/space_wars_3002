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
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('galaxies', function (Blueprint $table) {
            $table->dropForeign(['owner_user_id']);
            $table->dropColumn(['game_mode', 'owner_user_id']);
        });
    }
};
