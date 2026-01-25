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
        Schema::create('system_defenses', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('poi_id')->constrained('points_of_interest')->onDelete('cascade');

            // Defense type: orbital_cannon, space_laser, ground_missile, planetary_shield, fighter_port
            $table->string('defense_type', 50);

            // Level affects damage/effectiveness
            $table->unsignedTinyInteger('level')->default(1);

            // Quantity (for stackable defenses like fighters)
            $table->unsignedInteger('quantity')->default(1);

            // Current health (0 = destroyed)
            $table->unsignedInteger('health')->default(100);

            // Max health for restoration calculations
            $table->unsignedInteger('max_health')->default(100);

            // Active status (can be disabled)
            $table->boolean('is_active')->default(true);

            // Additional attributes (damage multipliers, special abilities, etc.)
            // JSON: {damage: int, range: int, cooldown: int, special: string}
            $table->json('attributes')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['poi_id', 'defense_type']);
            $table->index(['poi_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_defenses');
    }
};
