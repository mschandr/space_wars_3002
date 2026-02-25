<?php

namespace App\Enums;

enum ComponentType: string
{
    case WEAPON = 'weapon';
    case SHIELD = 'shield';
    case ARMOR = 'armor';
    case ENGINE = 'engine';
    case SENSOR = 'sensor';
    case FUEL_SYSTEM = 'fuel_system';
    case CARGO = 'cargo';
    case UTILITY = 'utility';

    /**
     * Which ship slot type this component occupies.
     */
    public function slotType(): SlotType
    {
        return match ($this) {
            self::WEAPON => SlotType::WEAPON,
            self::SHIELD => SlotType::SHIELD_GENERATOR,
            self::ARMOR => SlotType::HULL_PLATING,
            self::ENGINE => SlotType::ENGINE,
            self::SENSOR => SlotType::SENSOR_ARRAY,
            self::FUEL_SYSTEM => SlotType::REACTOR,
            self::CARGO => SlotType::CARGO_MODULE,
            self::UTILITY => SlotType::UTILITY,
        };
    }

    /**
     * Human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::WEAPON => 'Weapon',
            self::SHIELD => 'Shield',
            self::ARMOR => 'Armor',
            self::ENGINE => 'Engine',
            self::SENSOR => 'Sensor',
            self::FUEL_SYSTEM => 'Fuel System',
            self::CARGO => 'Cargo',
            self::UTILITY => 'Utility',
        };
    }
}
