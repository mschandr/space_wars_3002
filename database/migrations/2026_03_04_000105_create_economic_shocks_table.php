<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('economic_shocks', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('galaxy_id')->constrained()->cascadeOnDelete();
            $table->foreignId('commodity_id')->nullable()->constrained()->nullOnDelete();
            $table->comment('Nullable commodity_id = system-wide shock affecting all commodities');

            // Shock parameters
            $table->string('shock_type', 50); // DISCOVERY, BLOCKADE, DISASTER, BOOM
            $table->decimal('magnitude', 5, 3); // e.g., +0.25 = +25% price multiplier
            $table->integer('decay_half_life_ticks')->default(100); // Ticks until magnitude = 50%

            // Lifecycle
            $table->dateTime('starts_at')->index();
            $table->dateTime('ends_at')->nullable();
            $table->boolean('is_active')->default(true)->index();

            // Trigger info
            $table->bigInteger('triggered_by_actor_id')->nullable();
            $table->enum('triggered_by_actor_type', ['PLAYER', 'NPC', 'SYSTEM'])->nullable();

            // Metadata
            $table->json('metadata')->nullable(); // {deposit_id, reason, ...}

            $table->timestamps();

            // Query optimization
            $table->index(['galaxy_id', 'is_active']);
            $table->index(['commodity_id', 'is_active']);
            $table->index('shock_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('economic_shocks');
    }
};
