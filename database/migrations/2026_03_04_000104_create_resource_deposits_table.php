<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resource_deposits', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('galaxy_id')->constrained()->cascadeOnDelete();
            $table->foreignId('commodity_id')->constrained()->restrictOnDelete();

            // Extraction parameters
            $table->integer('quality')->default(100)->comment('0-100, affects extraction rate');
            $table->decimal('max_extraction_per_tick', 12, 4); // Units per tick
            $table->decimal('total_extracted', 14, 4)->default(0); // Cumulative tracking
            $table->decimal('max_total_qty', 14, 4)->nullable()->comment('Total available before depletion');

            // Discovery info
            $table->dateTime('discovered_at')->nullable();
            $table->bigInteger('discovered_by_actor_id')->nullable();
            $table->enum('discovered_by_actor_type', ['PLAYER', 'NPC'])->nullable();

            // Status
            $table->enum('status', ['ACTIVE', 'DEPLETED', 'BLOCKED'])->default('ACTIVE');

            // Metadata
            $table->json('metadata')->nullable(); // {sector_id, richness_notes, ...}

            $table->timestamps();

            // Indexes
            $table->index(['galaxy_id', 'commodity_id']);
            $table->index('status');
            $table->index('discovered_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resource_deposits');
    }
};
