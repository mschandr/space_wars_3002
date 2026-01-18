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
        Schema::create('combat_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // Combat type: pvp, pve_solo, pve_coop, colony_attack
            $table->string('combat_type');

            // Combat state
            $table->string('status')->default('active'); // active, completed, abandoned
            $table->integer('current_round')->default(1);

            // Location
            $table->foreignId('poi_id')->nullable()->constrained('points_of_interest')->onDelete('set null');

            // Results
            $table->string('victor_type')->nullable(); // player, team, defender, pirates
            $table->foreignId('victor_player_id')->nullable()->constrained('players')->onDelete('set null');
            $table->json('combat_log')->nullable(); // Full combat log
            $table->json('rewards')->nullable(); // Credits, XP, loot distributed

            // Related challenge (if PvP)
            $table->foreignId('pvp_challenge_id')->nullable()->constrained('pvp_challenges')->onDelete('cascade');

            // Related colony (if colony attack)
            $table->foreignId('target_colony_id')->nullable()->constrained('colonies')->onDelete('cascade');

            // Timestamps
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('combat_type');
            $table->index('status');
            $table->index('poi_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('combat_sessions');
    }
};
