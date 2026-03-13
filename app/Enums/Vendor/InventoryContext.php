<?php

namespace App\Enums\Vendor;

enum InventoryContext: string
{
    case NONE = 'none';
    case SHIP = 'ship';
    case SHIELD_PROJECTOR = 'shield_projector';
    case ENGINE = 'engine';
    case REACTOR = 'reactor';
    case WEAPON = 'weapon';
    case SENSOR_ARRAY = 'sensor_array';
    case CARGO_MODULE = 'cargo_module';
    case HULL_PLATING = 'hull_plating';
    case SALVAGE_COMPONENT = 'salvage_component';

    public function label(): string
    {
        return match ($this) {
            self::NONE => 'None',
            self::SHIP => 'Ship',
            self::SHIELD_PROJECTOR => 'Shield Projector',
            self::ENGINE => 'Engine',
            self::REACTOR => 'Reactor',
            self::WEAPON => 'Weapon',
            self::SENSOR_ARRAY => 'Sensor Array',
            self::CARGO_MODULE => 'Cargo Module',
            self::HULL_PLATING => 'Hull Plating',
            self::SALVAGE_COMPONENT => 'Salvage Component',
        };
    }
}
