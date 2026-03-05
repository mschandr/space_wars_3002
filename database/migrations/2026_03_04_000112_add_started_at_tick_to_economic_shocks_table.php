<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds started_at_tick field to track the game tick when a shock was created.
     * This allows proper exponential decay calculation using tick-based elapsed time
     * instead of mixing game ticks with Unix timestamps.
     */
    public function up(): void
    {
        Schema::table('economic_shocks', function (Blueprint $table) {
            $table->bigInteger('started_at_tick')->default(0)->after('starts_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('economic_shocks', function (Blueprint $table) {
            $table->dropColumn('started_at_tick');
        });
    }
};
