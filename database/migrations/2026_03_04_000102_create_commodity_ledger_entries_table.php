<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commodity_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->dateTime('timestamp')->index();

            // Location
            $table->foreignId('galaxy_id')->constrained()->cascadeOnDelete();
            $table->foreignId('trading_hub_id')->nullable()->constrained('trading_hubs')->nullOnDelete();

            // What moved
            $table->foreignId('commodity_id')->constrained()->restrictOnDelete();

            // Quantity and reason
            $table->decimal('qty_delta', 14, 4); // Positive = source, negative = sink
            $table->string('reason_code', 50); // MINING, CONSTRUCTION, UPKEEP, TRADE_BUY, TRADE_SELL, SALVAGE, NPC_INJECT, NPC_CONSUME, GENESIS

            // Who did it
            $table->enum('actor_type', ['PLAYER', 'NPC', 'SYSTEM'])->default('SYSTEM');
            $table->bigInteger('actor_id')->nullable();

            // Traceability
            $table->uuid('correlation_id')->nullable()->index(); // Ties multiple entries to one action
            $table->json('metadata')->nullable(); // {ship_id, blueprint_id, deposit_id, ...}

            $table->timestamps();

            // Critical indexes
            $table->index(['trading_hub_id', 'commodity_id', 'timestamp']);
            $table->index(['galaxy_id', 'commodity_id', 'timestamp']);
            $table->index('reason_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commodity_ledger_entries');
    }
};
