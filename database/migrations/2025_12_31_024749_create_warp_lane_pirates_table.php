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
        Schema::create('warp_lane_pirates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('warp_gate_id')->constrained('warp_gates')->onDelete('cascade');
            $table->foreignId('captain_id')->constrained('pirate_captains')->onDelete('cascade');
            $table->integer('fleet_size')->default(1);
            $table->integer('difficulty_tier')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_encounter_at')->nullable();
            $table->timestamps();

            $table->unique('warp_gate_id');
            $table->index(['is_active', 'difficulty_tier']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warp_lane_pirates');
    }
};
