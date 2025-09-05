<?php

namespace App\Enums\Legacy\CelestialBody;

enum PlanetType: string
{
    case CHTHONIC           = 'Chtonic';
    case GAS_GIANT          = 'Gas Giant';
    case HOT_JUPITER        = 'Hot Jupiter';
    case ICE_GIANT          = 'Ice Giant';
    case LAVA               = "Lava";
    case OCEAN              = 'Ocean';
    case ROGUE              = "Rogue";
    case SUPER_EARTH        = 'Super Earths';
    case TERRESTRIAL        = 'Terrestrial';

    public const BigPlanetType = [
        PlanetType::CHTHONIC->value,
        PlanetType::GAS_GIANT->value,
        PlanetType::HOT_JUPITER->value,
        PlanetType::ICE_GIANT->value,
        PlanetType::LAVA->value,
        PlanetType::OCEAN->value,
        PlanetType::ROGUE->value,
        PlanetType::SUPER_EARTH->value,
        PlanetType::TERRESTRIAL->value,
    ];

}
