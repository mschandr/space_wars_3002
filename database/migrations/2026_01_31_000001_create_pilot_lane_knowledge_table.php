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
        Schema::create('pilot_lane_knowledge', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->constrained()->onDelete('cascade');
            $table->foreignId('warp_gate_id')->constrained()->onDelete('cascade');
            $table->timestamp('discovered_at');
            $table->string('discovery_method', 50)->default('travel'); // travel, scan, chart, intel, spawn
            $table->boolean('pirate_risk_known')->default(false);
            $table->timestamp('last_pirate_check')->nullable();
            $table->timestamps();

            // Composite unique index - player can only know a lane once
            $table->unique(['player_id', 'warp_gate_id'], 'pilot_lane_unique');
            $table->index('player_id', 'pilot_lane_player');
            $table->index('warp_gate_id', 'pilot_lane_gate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pilot_lane_knowledge');
    }
};
