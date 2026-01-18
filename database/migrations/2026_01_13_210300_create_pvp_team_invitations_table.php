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
        Schema::create('pvp_team_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pvp_challenge_id')->constrained()->onDelete('cascade');
            $table->foreignId('invited_player_id')->constrained('players')->onDelete('cascade');
            $table->foreignId('invited_by_player_id')->constrained('players')->onDelete('cascade');
            $table->string('side'); // attacker, defender
            $table->string('status')->default('pending'); // pending, accepted, declined
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->unique(['pvp_challenge_id', 'invited_player_id'], 'pvp_team_invitations_unique');
            $table->index('invited_player_id');
            $table->index(['pvp_challenge_id', 'side']);
        });

        // Add max team size to pvp_challenges
        Schema::table('pvp_challenges', function (Blueprint $table) {
            $table->integer('max_team_size')->default(1)->after('wager_credits');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pvp_challenges', function (Blueprint $table) {
            $table->dropColumn('max_team_size');
        });

        Schema::dropIfExists('pvp_team_invitations');
    }
};
