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
        Schema::create('trading_hub_ships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trading_hub_id')->constrained()->onDelete('cascade');
            $table->foreignId('ship_id')->constrained()->onDelete('cascade');
            $table->integer('quantity')->default(1); // How many of this ship type are in stock
            $table->decimal('current_price', 12, 2); // May vary from base_price based on supply/demand
            $table->integer('demand_level')->default(50); // 0-100
            $table->integer('supply_level')->default(50); // 0-100
            $table->timestamp('last_price_update')->nullable();
            $table->timestamps();

            $table->unique(['trading_hub_id', 'ship_id']);
            $table->index('trading_hub_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trading_hub_ships');
    }
};
