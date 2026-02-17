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
    case UTILITY = 'utility';

    /**
     * Which ship slot type this component occupies.
     */
    public function slotType(): string
    {
        return match ($this) {
            self::WEAPON => 'weapon_slot',
            default => 'utility_slot',
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
            self::UTILITY => 'Utility',
        };
    }
}
