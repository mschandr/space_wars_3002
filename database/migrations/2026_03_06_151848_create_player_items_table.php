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
        Schema::create('player_items', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            // Foreign keys
            $table->foreignId('player_id')->constrained('players')->cascadeOnDelete();
            $table->foreignId('trading_hub_id')->nullable()->constrained('trading_hubs')->nullOnDelete();
            $table->foreignId('construction_job_id')->nullable()->constrained('construction_jobs')->nullOnDelete();

            // Item details
            $table->string('item_code'); // Denormalized from blueprint/construction_job
            $table->integer('quantity')->default(1);

            // Status: ready_for_pickup, claimed, consumed, destroyed
            $table->enum('status', ['ready_for_pickup', 'claimed', 'consumed', 'destroyed'])->default('ready_for_pickup');

            // Metadata
            $table->json('metadata')->nullable(); // {blueprint_id, output_type, ...}

            $table->timestamps();

            // Indexes
            $table->index(['player_id', 'status']);
            $table->index(['trading_hub_id', 'status']);
            $table->index('item_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('player_items');
    }
};
