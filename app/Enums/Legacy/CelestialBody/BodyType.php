<?php

namespace App\Enums\Legacy\CelestialBody;

enum BodyType: string
{
    case ASTEROID                   = 'Asteroid';
    case ASTEROID_BELT              = 'Asteroid Belt';
    case BLACK_HOLE                 = 'Black Hole';
    case CHTHONIC                   = 'Chthonic';
    case COMET                      = 'Comet';
    case DWARF_PLANET               = 'Dwarf planet';
    case GAS_GIANT                  = 'Gas Giant';
    case HOT_JUPITER                = 'Hot Jupiter';
    case ICE_GIANT                  = 'Ice Giant';
    case LAVA                       = "Lava";
    case MOON                       = 'Moon';
    case NEBULA                     = 'Nebula';
    case OCEAN                      = 'Ocean';
    case PLANET                     = 'Planet';
    case ROGUE                      = 'Rogue';
    case STAR                       = 'Star';
    case SUPER_EARTH                = 'Super Earths';
    case SUPER_MASSIVE_BLACK_HOLE   = 'Super Massive Black Hole';
    case TERRESTRIAL                = 'Terrestrial';


    public const UniverseBodyTypes = [
        BodyType::COMET->value,
        BodyType::BLACK_HOLE->value,
        BodyType::NEBULA->value,
        BodyType::ROGUE->value,
        BodyType::STAR->value,
        BodyType::SUPER_MASSIVE_BLACK_HOLE->value,
    ];

    public const SystemBodyTypes = [
        BodyType::ASTEROID->value,
        BodyType::ASTEROID_BELT->value,
        BodyType::CHTHONIC->value,
        BodyType::COMET->value,
        BodyType::DWARF_PLANET->value,
        BodyType::HOT_JUPITER->value,
        BodyType::ICE_GIANT->value,
        BodyType::LAVA->value,
        BodyType::MOON->value,
        BodyType::OCEAN->value,
        BodyType::PLANET->value,
        BodyType::SUPER_EARTH->value,
        BodyType::TERRESTRIAL->value,
    ];

    public static function whatBodyTypeIs($value): string
    {
        return in_array($value, self::UniverseBodyTypes) ? 'Universe' : 'System';
    }

    public function getPlanetType(): ?PlanetType
    {
        if ($this->isPlanet()) {
            return match ($this) {
                BodyType::CHTHONIC      => PlanetType::CHTHONIC,
                BodyType::GAS_GIANT     => PlanetType::GAS_GIANT,
                BodyType::HOT_JUPITER   => PlanetType::HOT_JUPITER,
                BodyType::ICE_GIANT     => PlanetType::ICE_GIANT,
                BodyType::LAVA          => PlanetType::LAVA,
                BodyType::OCEAN         => PlanetType::OCEAN,
                BodyType::ROGUE         => PlanetType::ROGUE,
                BodyType::SUPER_EARTH   => PlanetType::SUPER_EARTH,
                BodyType::TERRESTRIAL   => PlanetType::TERRESTRIAL,
                default                 => null,
            };
        }
    }

    public function isPlanet(): bool
    {
        return in_array($this, [
            BodyType::CHTHONIC,
            BodyType::GAS_GIANT,
            BodyType::HOT_JUPITER,
            BodyType::ICE_GIANT,
            BodyType::LAVA,
            BodyType::OCEAN,
            BodyType::ROGUE,
            BodyType::SUPER_EARTH,
            BodyType::TERRESTRIAL,
        ]);
    }
}
