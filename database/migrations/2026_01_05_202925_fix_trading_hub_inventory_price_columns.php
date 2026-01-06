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
        Schema::table('trading_hub_inventories', function (Blueprint $table) {
            // Increase decimal precision from (10,2) to (15,2) to handle larger prices
            // This allows values up to 9,999,999,999,999.99 (nearly 10 trillion)
            $table->decimal('current_price', 15, 2)->change();
            $table->decimal('buy_price', 15, 2)->change();
            $table->decimal('sell_price', 15, 2)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trading_hub_inventories', function (Blueprint $table) {
            // Revert to original precision
            $table->decimal('current_price', 10, 2)->change();
            $table->decimal('buy_price', 10, 2)->change();
            $table->decimal('sell_price', 10, 2)->change();
        });
    }
};
