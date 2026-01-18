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
        Schema::create('combat_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('combat_session_id')->constrained('combat_sessions')->onDelete('cascade');
            $table->foreignId('player_id')->constrained('players')->onDelete('cascade');
            $table->foreignId('player_ship_id')->nullable()->constrained('player_ships')->onDelete('set null');

            // Side: attacker, defender, ally_attacker, ally_defender
            $table->string('side');

            // Combat stats
            $table->integer('starting_hull');
            $table->integer('current_hull');
            $table->integer('damage_dealt')->default(0);
            $table->integer('damage_taken')->default(0);
            $table->boolean('survived')->default(true);

            // Rewards
            $table->integer('xp_earned')->default(0);
            $table->decimal('credits_earned', 15, 2)->default(0);
            $table->json('loot_received')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('combat_session_id');
            $table->index('player_id');
            $table->index(['combat_session_id', 'side']);
            $table->unique(['combat_session_id', 'player_id']); // Each player can only participate once per session
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('combat_participants');
    }
};
