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

            // Extraction parameters (all with DB-level validation)
            // Quality: 0-100, affects extraction rate
            $table->unsignedTinyInteger('quality')->default(100)->comment('Quality factor: 0-100');

            // Max extraction per tick: must be positive
            $table->decimal('max_extraction_per_tick', 12, 4)->unsigned()->comment('Units per tick (must be > 0)');

            // Total extracted so far: cumulative, non-negative
            $table->decimal('total_extracted', 14, 4)->unsigned()->default(0)->comment('Cumulative extraction (>= 0)');

            // Max total qty: total available before depletion (non-negative)
            $table->decimal('max_total_qty', 14, 4)->unsigned()->comment('Maximum total quantity available (>= 0)');

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

        // Add DB-level CHECK constraints for data integrity
        \DB::statement('ALTER TABLE resource_deposits ADD CONSTRAINT quality_range CHECK (quality >= 0 AND quality <= 100)');
        \DB::statement('ALTER TABLE resource_deposits ADD CONSTRAINT max_extraction_positive CHECK (max_extraction_per_tick > 0)');
        \DB::statement('ALTER TABLE resource_deposits ADD CONSTRAINT total_extracted_non_negative CHECK (total_extracted >= 0)');
        \DB::statement('ALTER TABLE resource_deposits ADD CONSTRAINT extraction_not_overdrawn CHECK (total_extracted <= max_total_qty)');
    }

    public function down(): void
    {
        // Drop constraints before dropping table (for cleaner rollback)
        \DB::statement('ALTER TABLE resource_deposits DROP CONSTRAINT IF EXISTS quality_range');
        \DB::statement('ALTER TABLE resource_deposits DROP CONSTRAINT IF EXISTS max_extraction_positive');
        \DB::statement('ALTER TABLE resource_deposits DROP CONSTRAINT IF EXISTS total_extracted_non_negative');
        \DB::statement('ALTER TABLE resource_deposits DROP CONSTRAINT IF EXISTS extraction_not_overdrawn');

        Schema::dropIfExists('resource_deposits');
    }
};
