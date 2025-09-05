<?php

namespace App\Enums\PointsOfInterest;

enum PointOfInterestType: string
{
    case ASTEROID                   = 'Asteroid';
    case ASTEROID_BELT              = 'Asteroid Belt';
    case BLACK_HOLE                 = 'Black Hole';
    case CHTHONIC                   = 'Chthonic';
    case COMET                      = 'Comet';
    case DWARF_PLANET               = 'Dwarf Planet';
    case GAS_GIANT                  = 'Gas Giant';
    case HOT_JUPITER                = 'Hot Jupiter';
    case ICE_GIANT                  = 'Ice Giant';
    case LAVA                       = 'Lava';
    case MOON                       = 'Moon';
    case NEBULA                     = 'Nebula';
    case OCEAN                      = 'Ocean';
    case PLANET                     = 'Planet';
    case ROGUE                      = 'Rogue';
    case STAR                       = 'Star';
    case SUPER_EARTH                = 'Super Earth';
    case SUPER_MASSIVE_BLACK_HOLE   = 'Super Massive Black Hole';
    case TERRESTRIAL                = 'Terrestrial';

    public const UniverseTypes = [
        self::COMET,
        self::BLACK_HOLE,
        self::NEBULA,
        self::ROGUE,
        self::STAR,
        self::SUPER_MASSIVE_BLACK_HOLE,
    ];

    public const SystemTypes = [
        self::ASTEROID,
        self::ASTEROID_BELT,
        self::CHTHONIC,
        self::COMET,
        self::DWARF_PLANET,
        self::HOT_JUPITER,
        self::ICE_GIANT,
        self::LAVA,
        self::MOON,
        self::OCEAN,
        self::PLANET,
        self::SUPER_EARTH,
        self::TERRESTRIAL,
    ];

    public static function whatTypeIs(self $value): string
    {
        return in_array($value, self::UniverseTypes, true) ? 'Universe' : 'System';
    }

    public function domain(): string
    {
        return match (true) {
            in_array($this, self::UniverseTypes, true) => 'Universe',
            in_array($this, self::SystemTypes, true)   => 'System',
            default => 'Unknown',
        };
    }

    public function isPlanet(): bool
    {
        return match ($this) {
            self::CHTHONIC,
            self::GAS_GIANT,
            self::HOT_JUPITER,
            self::ICE_GIANT,
            self::LAVA,
            self::OCEAN,
            self::ROGUE,
            self::SUPER_EARTH,
            self::TERRESTRIAL => true,
            default => false,
        };
    }

}
