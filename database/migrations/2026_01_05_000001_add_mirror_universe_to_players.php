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
            $table->timestamp('mirror_universe_entry_time')
                ->nullable()
                ->after('last_trading_hub_poi_id')
                ->comment('Timestamp when player entered mirror universe (for cooldown tracking)');

            $table->index('mirror_universe_entry_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->dropIndex(['mirror_universe_entry_time']);
            $table->dropColumn('mirror_universe_entry_time');
        });
    }
};
