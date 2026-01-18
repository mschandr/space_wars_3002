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
        Schema::table('combat_participants', function (Blueprint $table) {
            $table->foreignId('player_id')->nullable()->change();
            $table->foreignId('player_ship_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('combat_participants', function (Blueprint $table) {
            $table->foreignId('player_id')->nullable(false)->change();
            $table->foreignId('player_ship_id')->nullable(false)->change();
        });
    }
};
