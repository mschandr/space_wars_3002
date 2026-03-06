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
        Schema::create('construction_jobs', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            // Foreign keys
            $table->foreignId('galaxy_id')->constrained('galaxies')->cascadeOnDelete();
            $table->foreignId('trading_hub_id')->constrained('trading_hubs')->cascadeOnDelete();
            $table->foreignId('player_id')->constrained('players')->cascadeOnDelete();
            $table->foreignId('blueprint_id')->constrained('blueprints')->restrictOnDelete();

            // Build details
            $table->integer('quantity')->default(1); // How many units being built
            $table->enum('status', ['PENDING', 'COMPLETE', 'FAILED'])->default('PENDING');

            // Input snapshot (for historical accuracy)
            $table->json('inputs_consumed')->nullable(); // [{commodity_id, qty_each, total_qty}, ...]

            // Output reference
            $table->string('output_item_code'); // Denormalized from blueprint

            // Timing
            $table->dateTime('started_at');
            $table->dateTime('completes_at');
            $table->dateTime('completed_at')->nullable();

            // Additional metadata
            $table->json('metadata')->nullable();

            // Indexes for tick queries and lookups
            $table->index(['status', 'completes_at']);
            $table->index(['player_id', 'status']);
            $table->index(['galaxy_id']);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('construction_jobs');
    }
};
