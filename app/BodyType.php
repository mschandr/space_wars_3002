<?php

namespace App;

enum BodyType: string
{
    case Moon = 'Moon';
    case AsteroidBelt = 'Asteroid Belt';
    case BlackHole = 'Black Hole';
    case Planet = 'Planet';
    case Star = 'Star';
    case Nebula = 'Nebula';
    case Comet = 'Comet';
    case Asteroid = 'Asteroid';
    case DwarfPlanet = 'Dwarf planet';


    public const UniverseBodyTypes = [BodyType::BlackHole->value, BodyType::Nebula->value, BodyType::Star->value];
    public const SystemBodyTypes = [
        BodyType::Moon->value, BodyType::Asteroid->value, BodyType::Planet->value, BodyType::Comet->value,
        BodyType::DwarfPlanet->value, BodyType::AsteroidBelt->value
    ];

    public function whatBodyTypeIs(): string
    {
        return match ($this) {
            BodyType::BlackHole, BodyType::Nebula, BodyType::Star => 'Universe',
            BodyType::Moon, BodyType::Asteroid, BodyType::Planet, BodyType::Comet,
            BodyType::DwarfPlanet, BodyType::AsteroidBelt => 'System',
        };
    }

}
