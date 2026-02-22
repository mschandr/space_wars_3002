<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_price_sightings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->foreignId('trading_hub_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mineral_id')->constrained()->cascadeOnDelete();
            $table->decimal('buy_price', 15, 2);
            $table->decimal('sell_price', 15, 2);
            $table->integer('quantity');
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->index(['player_id', 'trading_hub_id', 'mineral_id', 'recorded_at'], 'sightings_player_hub_mineral_time');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_price_sightings');
    }
};
