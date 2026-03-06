<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reserve_policies', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('galaxy_id')->constrained()->cascadeOnDelete();
            $table->foreignId('commodity_id')->nullable()->constrained()->nullOnDelete();

            // Reserve quantities and NPC pricing multiplier
            $table->decimal('min_qty_on_hand', 14, 4);
            $table->boolean('npc_fallback_enabled')->default(true);
            $table->decimal('npc_price_multiplier', 5, 2)->default(1.5);

            $table->text('description')->nullable();

            $table->timestamps();

            $table->unique(['galaxy_id', 'commodity_id']);
            $table->index('galaxy_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reserve_policies');
    }
};
