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
        Schema::create('trading_hub_inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trading_hub_id')->constrained('trading_hubs')->onDelete('cascade');
            $table->foreignId('mineral_id')->constrained('minerals')->onDelete('cascade');
            $table->integer('quantity')->default(0);
            $table->decimal('current_price', 10, 2); // Current market price (fluctuates)
            $table->decimal('buy_price', 10, 2); // Price hub buys at
            $table->decimal('sell_price', 10, 2); // Price hub sells at
            $table->integer('demand_level')->default(50); // 0-100, affects pricing
            $table->integer('supply_level')->default(50); // 0-100, affects pricing
            $table->timestamp('last_price_update')->nullable();
            $table->timestamps();

            $table->unique(['trading_hub_id', 'mineral_id']);
            $table->index('mineral_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trading_hub_inventories');
    }
};
