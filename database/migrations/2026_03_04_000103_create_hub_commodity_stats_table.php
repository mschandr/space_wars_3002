<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hub_commodity_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trading_hub_id')->constrained('trading_hubs')->cascadeOnDelete();
            $table->foreignId('commodity_id')->constrained()->cascadeOnDelete();

            // Rolling averages (derived from ledger, updated each tick)
            $table->decimal('avg_daily_demand', 12, 4)->default(0);
            $table->decimal('avg_daily_supply', 12, 4)->default(0);

            // Cached prices for performance
            $table->decimal('cached_buy_price', 12, 2)->nullable();
            $table->decimal('cached_sell_price', 12, 2)->nullable();

            $table->dateTime('last_computed_at')->nullable();
            $table->timestamps();

            // Unique constraint: one stat entry per hub+commodity
            $table->unique(['trading_hub_id', 'commodity_id']);

            // Query optimization
            $table->index('trading_hub_id');
            $table->index('commodity_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hub_commodity_stats');
    }
};
