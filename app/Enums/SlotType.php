<?php

namespace App\Enums;

enum SlotType: string
{
    case ENGINE = 'engine';
    case REACTOR = 'reactor';
    case HULL_PLATING = 'hull_plating';
    case SHIELD_GENERATOR = 'shield_generator';
    case SENSOR_ARRAY = 'sensor_array';
    case CARGO_MODULE = 'cargo_module';
    case WEAPON = 'weapon';
    case UTILITY = 'utility';

    /**
     * Human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::ENGINE => 'Engine',
            self::REACTOR => 'Reactor',
            self::HULL_PLATING => 'Hull Plating',
            self::SHIELD_GENERATOR => 'Shield Generator',
            self::SENSOR_ARRAY => 'Sensor Array',
            self::CARGO_MODULE => 'Cargo Module',
            self::WEAPON => 'Weapon',
            self::UTILITY => 'Utility',
        };
    }

    /**
     * Core systems occupy a single dedicated slot per ship.
     * Multi-slot types (weapon/utility) can have multiple slots.
     */
    public function isCoreSystem(): bool
    {
        return match ($this) {
            self::ENGINE,
            self::REACTOR,
            self::HULL_PLATING,
            self::SHIELD_GENERATOR,
            self::SENSOR_ARRAY,
            self::CARGO_MODULE => true,
            self::WEAPON,
            self::UTILITY => false,
        };
    }

    /**
     * Multi-slot types allow multiple components of this type.
     */
    public function isMultiSlot(): bool
    {
        return ! $this->isCoreSystem();
    }

    /**
     * Get the corresponding ship/player_ship column for slot count.
     */
    public function slotColumn(): string
    {
        return match ($this) {
            self::ENGINE => 'engine_slots',
            self::REACTOR => 'reactor_slots',
            self::HULL_PLATING => 'hull_plating_slots',
            self::SHIELD_GENERATOR => 'shield_slots',
            self::SENSOR_ARRAY => 'sensor_slots',
            self::CARGO_MODULE => 'cargo_module_slots',
            self::WEAPON => 'weapon_slots',
            self::UTILITY => 'utility_slots',
        };
    }
}
