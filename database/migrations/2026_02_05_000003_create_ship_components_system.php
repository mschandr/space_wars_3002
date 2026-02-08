<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create ship components system for salvage yards.
     *
     * Components are items that can be installed into weapon_slots and utility_slots:
     * - Weapons: Lasers, missiles, torpedoes (fill weapon_slots)
     * - Utilities: Shield regenerators, hull patches, cargo expanders (fill utility_slots)
     */
    public function up(): void
    {
        // Ship component blueprints (what components exist in the game)
        Schema::create('ship_components', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('type'); // weapon, shield, hull, utility
            $table->string('slot_type'); // weapon_slot, utility_slot
            $table->text('description')->nullable();
            $table->integer('slots_required')->default(1);
            $table->decimal('base_price', 12, 2)->default(0);
            $table->string('rarity')->default('common'); // common, uncommon, rare, very_rare, legendary
            $table->json('effects'); // {"damage": 50, "accuracy": 0.85} or {"shield_regen": 5, "hull_repair": 10}
            $table->json('requirements')->nullable(); // {"level": 5, "sensors": 3}
            $table->boolean('is_available')->default(true);
            $table->timestamps();

            $table->index('type');
            $table->index('slot_type');
            $table->index('rarity');
        });

        // Components installed on player ships
        Schema::create('player_ship_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_ship_id')->constrained()->onDelete('cascade');
            $table->foreignId('ship_component_id')->constrained()->onDelete('restrict');
            $table->string('slot_type'); // weapon_slot, utility_slot
            $table->integer('slot_index'); // Which slot it's in (1, 2, 3, etc.)
            $table->integer('condition')->default(100); // 0-100, degrades with use
            $table->integer('ammo')->nullable(); // For weapons that use ammo
            $table->integer('max_ammo')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['player_ship_id', 'slot_type', 'slot_index'], 'ship_slot_unique');
            $table->index('player_ship_id');
        });

        // Salvage yard inventory (what's for sale at each trading hub with salvage yard)
        Schema::create('salvage_yard_inventory', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trading_hub_id')->constrained()->onDelete('cascade');
            $table->foreignId('ship_component_id')->constrained()->onDelete('cascade');
            $table->integer('quantity')->default(1);
            $table->decimal('current_price', 12, 2); // May differ from base_price
            $table->integer('condition')->default(100); // Salvage items may be damaged
            $table->string('source')->default('salvage'); // salvage, manufactured, stolen
            $table->timestamps();

            $table->index(['trading_hub_id', 'ship_component_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salvage_yard_inventory');
        Schema::dropIfExists('player_ship_components');
        Schema::dropIfExists('ship_components');
    }
};
