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
        Schema::table('players', function (Blueprint $table) {
            // Drop the global unique constraint on call_sign
            $table->dropUnique(['call_sign']);

            // Add galaxy-scoped unique constraint (call_sign must be unique per galaxy)
            $table->unique(['galaxy_id', 'call_sign'], 'players_galaxy_call_sign_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('players', function (Blueprint $table) {
            // Drop the galaxy-scoped unique constraint
            $table->dropUnique('players_galaxy_call_sign_unique');

            // Restore global unique constraint on call_sign
            $table->unique('call_sign');
        });
    }
};
