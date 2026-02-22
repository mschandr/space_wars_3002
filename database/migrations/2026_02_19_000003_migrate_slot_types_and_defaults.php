<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // =====================================================================
        // Remap ship_components.slot_type from old 2-slot to new 8-slot system
        // =====================================================================

        // Weapons: weapon_slot -> weapon
        DB::table('ship_components')
            ->where('type', 'weapon')
            ->update(['slot_type' => 'weapon']);

        // Shields: utility_slot -> shield_generator
        DB::table('ship_components')
            ->where('type', 'shield')
            ->update(['slot_type' => 'shield_generator']);

        // Hull/Armor: utility_slot -> hull_plating
        DB::table('ship_components')
            ->where('type', 'hull')
            ->update(['slot_type' => 'hull_plating']);

        // Engines: utility_slot -> engine
        DB::table('ship_components')
            ->where('type', 'engine')
            ->update(['slot_type' => 'engine']);

        // Sensors: utility_slot -> sensor_array (by type or effects)
        DB::table('ship_components')
            ->where('type', 'sensor')
            ->update(['slot_type' => 'sensor_array']);

        // Fuel systems: utility_slot -> reactor (by type or effects)
        DB::table('ship_components')
            ->where('type', 'fuel_system')
            ->update(['slot_type' => 'reactor']);

        // Cargo: utility_slot -> cargo_module
        DB::table('ship_components')
            ->where('type', 'cargo')
            ->update(['slot_type' => 'cargo_module']);

        // Catch remaining utilities with specific effects
        // Fuel-related effects -> reactor
        DB::table('ship_components')
            ->where('slot_type', 'utility_slot')
            ->where(function ($query) {
                $query->where('effects', 'like', '%fuel_boost%')
                    ->orWhere('effects', 'like', '%fuel_regen_boost%')
                    ->orWhere('effects', 'like', '%fuel_efficiency%');
            })
            ->update(['slot_type' => 'reactor']);

        // Sensor-related effects -> sensor_array
        DB::table('ship_components')
            ->where('slot_type', 'utility_slot')
            ->where(function ($query) {
                $query->where('effects', 'like', '%sensor_boost%')
                    ->orWhere('effects', 'like', '%pirate_detection%');
            })
            ->update(['slot_type' => 'sensor_array']);

        // Cargo-related effects -> cargo_module
        DB::table('ship_components')
            ->where('slot_type', 'utility_slot')
            ->where('effects', 'like', '%cargo_boost%')
            ->update(['slot_type' => 'cargo_module']);

        // Everything remaining as utility_slot -> utility
        DB::table('ship_components')
            ->where('slot_type', 'utility_slot')
            ->update(['slot_type' => 'utility']);

        // =====================================================================
        // Remap player_ship_components.slot_type based on their blueprint
        // =====================================================================
        $componentSlotTypes = DB::table('ship_components')
            ->select('id', 'slot_type')
            ->get();

        foreach ($componentSlotTypes as $sc) {
            DB::table('player_ship_components')
                ->where('ship_component_id', $sc->id)
                ->update(['slot_type' => $sc->slot_type]);
        }

        // =====================================================================
        // Set max_upgrade_level based on rarity
        // =====================================================================
        $upgradesByRarity = [
            'common' => [5, 7],
            'uncommon' => [3, 5],
            'rare' => [2, 3],
            'epic' => [1, 2],
            'unique' => [1, 1],
            'exotic' => [0, 0],
        ];

        foreach ($upgradesByRarity as $rarity => [$min, $max]) {
            $level = (int) round(($min + $max) / 2);
            DB::table('ship_components')
                ->where('rarity', $rarity)
                ->update(['max_upgrade_level' => $level]);
        }

        // =====================================================================
        // Set upgrade_cost_base = base_price * 0.3
        // =====================================================================
        DB::table('ship_components')
            ->update(['upgrade_cost_base' => DB::raw('base_price * 0.3')]);

        // =====================================================================
        // Populate ship slot counts per class
        // =====================================================================
        $shipSlots = [
            'starter' => ['size_class' => 'small', 'engine_slots' => 1, 'reactor_slots' => 1, 'hull_plating_slots' => 1, 'shield_slots' => 1, 'sensor_slots' => 1, 'cargo_module_slots' => 1],
            'fighter' => ['size_class' => 'small', 'engine_slots' => 1, 'reactor_slots' => 1, 'hull_plating_slots' => 1, 'shield_slots' => 1, 'sensor_slots' => 1, 'cargo_module_slots' => 1],
            'smuggler' => ['size_class' => 'medium', 'engine_slots' => 1, 'reactor_slots' => 1, 'hull_plating_slots' => 1, 'shield_slots' => 1, 'sensor_slots' => 1, 'cargo_module_slots' => 1],
            'explorer' => ['size_class' => 'medium', 'engine_slots' => 1, 'reactor_slots' => 1, 'hull_plating_slots' => 1, 'shield_slots' => 1, 'sensor_slots' => 2, 'cargo_module_slots' => 1],
            'mining' => ['size_class' => 'medium', 'engine_slots' => 1, 'reactor_slots' => 1, 'hull_plating_slots' => 1, 'shield_slots' => 1, 'sensor_slots' => 1, 'cargo_module_slots' => 2],
            'cargo' => ['size_class' => 'large', 'engine_slots' => 1, 'reactor_slots' => 1, 'hull_plating_slots' => 1, 'shield_slots' => 1, 'sensor_slots' => 1, 'cargo_module_slots' => 3],
            'battleship' => ['size_class' => 'capital', 'engine_slots' => 1, 'reactor_slots' => 2, 'hull_plating_slots' => 3, 'shield_slots' => 2, 'sensor_slots' => 1, 'cargo_module_slots' => 1],
            'carrier' => ['size_class' => 'capital', 'engine_slots' => 1, 'reactor_slots' => 2, 'hull_plating_slots' => 2, 'shield_slots' => 2, 'sensor_slots' => 2, 'cargo_module_slots' => 1],
            'colony_ship' => ['size_class' => 'capital', 'engine_slots' => 1, 'reactor_slots' => 2, 'hull_plating_slots' => 2, 'shield_slots' => 1, 'sensor_slots' => 1, 'cargo_module_slots' => 2],
            'precursor' => ['size_class' => 'capital', 'engine_slots' => 1, 'reactor_slots' => 1, 'hull_plating_slots' => 1, 'shield_slots' => 1, 'sensor_slots' => 1, 'cargo_module_slots' => 1],
        ];

        foreach ($shipSlots as $class => $slots) {
            DB::table('ships')
                ->where('class', $class)
                ->update($slots);
        }

        // Also update existing player_ships based on their blueprint
        $shipBlueprints = DB::table('ships')
            ->select('id', 'engine_slots', 'reactor_slots', 'hull_plating_slots', 'shield_slots', 'sensor_slots', 'cargo_module_slots', 'size_class')
            ->get();

        foreach ($shipBlueprints as $blueprint) {
            DB::table('player_ships')
                ->where('ship_id', $blueprint->id)
                ->update([
                    'engine_slots' => $blueprint->engine_slots,
                    'reactor_slots' => $blueprint->reactor_slots,
                    'hull_plating_slots' => $blueprint->hull_plating_slots,
                    'shield_slots' => $blueprint->shield_slots,
                    'sensor_slots' => $blueprint->sensor_slots,
                    'cargo_module_slots' => $blueprint->cargo_module_slots,
                    'size_class' => $blueprint->size_class,
                ]);
        }
    }

    public function down(): void
    {
        // Revert slot_type values
        DB::table('ship_components')
            ->where('type', 'weapon')
            ->update(['slot_type' => 'weapon_slot']);

        DB::table('ship_components')
            ->whereIn('type', ['shield', 'hull', 'engine', 'sensor', 'fuel_system', 'cargo', 'utility'])
            ->update(['slot_type' => 'utility_slot']);

        DB::table('player_ship_components')
            ->update(['slot_type' => DB::raw("
                CASE
                    WHEN slot_type = 'weapon' THEN 'weapon_slot'
                    ELSE 'utility_slot'
                END
            ")]);

        // Reset ship slot counts
        DB::table('ships')->update([
            'engine_slots' => 1,
            'reactor_slots' => 1,
            'hull_plating_slots' => 1,
            'shield_slots' => 1,
            'sensor_slots' => 1,
            'cargo_module_slots' => 1,
            'size_class' => 'medium',
        ]);

        DB::table('player_ships')->update([
            'engine_slots' => 1,
            'reactor_slots' => 1,
            'hull_plating_slots' => 1,
            'shield_slots' => 1,
            'sensor_slots' => 1,
            'cargo_module_slots' => 1,
            'size_class' => 'medium',
        ]);
    }
};
