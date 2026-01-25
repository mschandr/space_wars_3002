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
        Schema::create('npcs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('galaxy_id')->constrained()->onDelete('cascade');
            $table->foreignId('current_poi_id')->nullable()->constrained('points_of_interest')->onDelete('set null');
            $table->foreignId('last_trading_hub_poi_id')->nullable()->constrained('points_of_interest')->onDelete('set null');

            // Identity
            $table->string('call_sign', 50);
            $table->string('archetype', 50)->default('trader'); // trader, explorer, pirate_hunter, miner, merchant

            // Stats (mirror Player model)
            $table->decimal('credits', 20, 2)->default(10000.00);
            $table->integer('experience')->default(0);
            $table->integer('level')->default(1);
            $table->integer('ships_destroyed')->default(0);
            $table->integer('combats_won')->default(0);
            $table->integer('combats_lost')->default(0);
            $table->decimal('total_trade_volume', 20, 2)->default(0.00);

            // AI Behavior configuration
            $table->string('difficulty', 20)->default('medium'); // easy, medium, hard, expert
            $table->float('aggression')->default(0.3); // 0.0-1.0, likelihood to engage in combat
            $table->float('risk_tolerance')->default(0.5); // 0.0-1.0, willingness to take risky actions
            $table->float('trade_focus')->default(0.7); // 0.0-1.0, focus on trading vs other activities
            $table->json('personality')->nullable(); // Additional personality traits

            // State tracking
            $table->string('status', 20)->default('active'); // active, inactive, destroyed
            $table->string('current_activity', 50)->nullable(); // trading, traveling, idle, combat
            $table->timestamp('last_action_at')->nullable();
            $table->timestamp('last_mirror_travel_at')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['galaxy_id', 'status']);
            $table->index(['galaxy_id', 'archetype']);
            $table->unique(['galaxy_id', 'call_sign']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('npcs');
    }
};
