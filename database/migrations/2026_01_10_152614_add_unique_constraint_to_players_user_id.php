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
            // Add galaxy_id foreign key as nullable first
            $table->foreignId('galaxy_id')->nullable()->after('user_id')->constrained()->onDelete('cascade');
        });

        // Assign existing players to the first available galaxy
        $firstGalaxy = \App\Models\Galaxy::first();
        if ($firstGalaxy) {
            \DB::table('players')->whereNull('galaxy_id')->update(['galaxy_id' => $firstGalaxy->id]);
        }

        Schema::table('players', function (Blueprint $table) {
            // Make galaxy_id non-nullable
            $table->foreignId('galaxy_id')->nullable(false)->change();

            // Add unique constraint to ensure one player per user per galaxy
            $table->unique(['user_id', 'galaxy_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'galaxy_id']);
            $table->dropForeign(['galaxy_id']);
            $table->dropColumn('galaxy_id');
        });
    }
};
