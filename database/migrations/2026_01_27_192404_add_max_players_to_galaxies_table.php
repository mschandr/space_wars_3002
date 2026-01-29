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
            $table->unsignedInteger('max_players')->nullable()->after('game_mode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('galaxies', function (Blueprint $table) {
            $table->dropColumn('max_players');
        });
    }
};
