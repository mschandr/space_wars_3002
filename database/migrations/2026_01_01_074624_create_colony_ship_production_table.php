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
        Schema::create('colony_ship_production', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('colony_id')->constrained('colonies')->onDelete('cascade');
            $table->foreignId('ship_id')->constrained('ships'); // Ship type being produced
            $table->foreignId('player_id')->constrained('players')->onDelete('cascade');

            // Production stats
            $table->integer('production_progress')->default(0); // 0-100%
            $table->integer('production_cost_credits');
            $table->integer('production_cost_minerals');
            $table->integer('production_time_cycles')->default(10); // How many cycles to complete

            // Status
            $table->string('status')->default('queued'); // queued, building, completed, cancelled
            $table->integer('queue_position')->default(1);

            // Timestamps
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('colony_id');
            $table->index('player_id');
            $table->index(['colony_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('colony_ship_production');
    }
};
