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
        Schema::create('player_ships', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('player_id')->constrained()->onDelete('cascade');
            $table->foreignId('ship_id')->constrained()->onDelete('restrict');
            $table->string('name')->nullable();

            // Fuel system
            $table->integer('current_fuel')->default(100);
            $table->integer('max_fuel')->default(100);
            $table->timestamp('fuel_last_updated_at')->useCurrent();

            // Ship components (upgradeable)
            $table->integer('hull')->default(100); // Defense
            $table->integer('max_hull')->default(100);
            $table->integer('weapons')->default(10); // Attack power
            $table->integer('cargo_hold')->default(10); // Transport capacity
            $table->integer('sensors')->default(1); // Detect hidden systems/gates
            $table->integer('warp_drive')->default(1); // Fuel efficiency

            // Current cargo
            $table->integer('current_cargo')->default(0);

            // Status
            $table->boolean('is_active')->default(false);
            $table->string('status')->default('operational'); // operational, damaged, destroyed

            $table->timestamps();

            $table->index('player_id');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('player_ships');
    }
};
