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
            $table->integer('ships_destroyed')->default(0)->after('level');
            $table->integer('combats_won')->default(0)->after('ships_destroyed');
            $table->integer('combats_lost')->default(0)->after('combats_won');
            $table->decimal('total_trade_volume', 15, 2)->default(0)->after('combats_lost');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->dropColumn(['ships_destroyed', 'combats_won', 'combats_lost', 'total_trade_volume']);
        });
    }
};
