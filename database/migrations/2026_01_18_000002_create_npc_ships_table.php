<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the `npc_ships` table with identifiers, foreign keys, ship attributes, state fields, and indexes.
     *
     * The table includes a primary id, unique `uuid`, foreign keys `npc_id` and `ship_id` (cascade on delete),
     * ship identity (`name`), ship stats (fuel, hull, weapons, cargo_hold, sensors, warp_drive, current_cargo),
     * state fields (`is_active`, `status`), timestamp for fuel updates, and automatic `created_at`/`updated_at`.
     * Adds a composite index on `npc_id` and `is_active`.
     */
    public function up(): void
    {
        Schema::create('npc_ships', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('npc_id')->constrained()->onDelete('cascade');
            $table->foreignId('ship_id')->constrained()->onDelete('cascade');

            // Ship identity
            $table->string('name', 100);

            // Ship stats (mirror PlayerShip model)
            $table->integer('current_fuel')->default(100);
            $table->integer('max_fuel')->default(100);
            $table->timestamp('fuel_last_updated_at')->nullable();
            $table->integer('hull')->default(100);
            $table->integer('max_hull')->default(100);
            $table->integer('weapons')->default(10);
            $table->integer('cargo_hold')->default(100);
            $table->integer('sensors')->default(1);
            $table->integer('warp_drive')->default(1);
            $table->integer('current_cargo')->default(0);

            // Ship state
            $table->boolean('is_active')->default(true);
            $table->string('status', 20)->default('operational'); // operational, damaged, destroyed

            $table->timestamps();

            // Indexes
            $table->index(['npc_id', 'is_active']);
        });
    }

    /**
     * Drop the `npc_ships` table if it exists.
     */
    public function down(): void
    {
        Schema::dropIfExists('npc_ships');
    }
};