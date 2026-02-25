<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_trade_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->foreignId('trading_hub_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mineral_id')->constrained()->cascadeOnDelete();
            $table->string('transaction_type'); // 'buy' or 'sell'
            $table->integer('quantity');
            $table->decimal('unit_price', 15, 2);
            $table->decimal('total_amount', 15, 2);
            $table->decimal('credits_after', 15, 2);
            $table->timestamp('transacted_at');
            $table->timestamps();

            $table->index(['player_id', 'transacted_at']);
            $table->index(['player_id', 'mineral_id']);
            $table->index(['trading_hub_id', 'mineral_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_trade_transactions');
    }
};
