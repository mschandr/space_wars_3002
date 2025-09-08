<?php

namespace App\Enums\PointsOfInterest;

enum PointOfInterestType: int
{
    case ANOMALY                  = 1;
    case ASTEROID                 = 2;
    case ASTEROID_BELT            = 3;
    case BLACK_HOLE               = 4;
    case CHTHONIC                 = 5;
    case COMET                    = 6;
    case DWARF_PLANET             = 7;
    case GAS_GIANT                = 8;
    case HOT_JUPITER              = 9;
    case ICE_GIANT                = 10;
    case LAVA                     = 11;
    case MOON                     = 12;
    case NEBULA                   = 13;
    case OCEAN                    = 14;
    case PLANET                   = 15;
    case ROGUE_PLANET             = 16;
    case STAR                     = 17;
    case SUPER_EARTH              = 18;
    case SUPER_MASSIVE_BLACK_HOLE = 19;
    case TERRESTRIAL              = 20;

    /** Labels for display */
    public function label(): string
    {
        return match ($this) {
            self::ANOMALY                  => 'Anomaly',
            self::ASTEROID                 => 'Asteroid',
            self::ASTEROID_BELT            => 'Asteroid Belt',
            self::BLACK_HOLE               => 'Black Hole',
            self::CHTHONIC                 => 'Chthonic',
            self::COMET                    => 'Comet',
            self::DWARF_PLANET             => 'Dwarf Planet',
            self::GAS_GIANT                => 'Gas Giant',
            self::HOT_JUPITER              => 'Hot Jupiter',
            self::ICE_GIANT                => 'Ice Giant',
            self::LAVA                     => 'Lava',
            self::MOON                     => 'Moon',
            self::NEBULA                   => 'Nebula',
            self::OCEAN                    => 'Ocean',
            self::PLANET                   => 'Planet',
            self::ROGUE_PLANET             => 'Rogue Planet',
            self::STAR                     => 'Star',
            self::SUPER_EARTH              => 'Super Earth',
            self::SUPER_MASSIVE_BLACK_HOLE => 'Super Massive Black Hole',
            self::TERRESTRIAL              => 'Terrestrial',
        };
    }

    public function domain(): string
    {
        return match ($this) {
            self::COMET,
            self::BLACK_HOLE,
            self::NEBULA,
            self::ROGUE_PLANET,
            self::STAR,
            self::SUPER_MASSIVE_BLACK_HOLE => 'Universe',

            self::ASTEROID,
            self::ASTEROID_BELT,
            self::CHTHONIC,
            self::DWARF_PLANET,
            self::GAS_GIANT,
            self::HOT_JUPITER,
            self::ICE_GIANT,
            self::LAVA,
            self::MOON,
            self::OCEAN,
            self::PLANET,
            self::SUPER_EARTH,
            self::TERRESTRIAL => 'System',

            default => 'Unknown',
        };
    }

    public function isUniverseType(): bool
    {
        return match ($this) {
            self::COMET, self::BLACK_HOLE, self::NEBULA, self::ROGUE_PLANET, self::STAR,
            self::SUPER_MASSIVE_BLACK_HOLE => true,
            default => false,
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
            self::ROGUE_PLANET,
            self::SUPER_EARTH,
            self::TERRESTRIAL,
            self::PLANET => true,
            default => false,
        };
    }
}
