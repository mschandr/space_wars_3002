<?php

namespace App\Faker\Providers;

use Faker\Provider\Base;

class NebulaNameProvider extends Base
{
    protected static array $nebulae = [
        'Pleiades', 'Lagoon', 'Trifid', 'Cone', 'Butterfly',
        "Cat's Eye", 'Little Ghost', 'Reflective', 'Ghost of Jupiter',
        'Blue Snowman', 'Flaming Star', 'Southern Crab', 'Monkey Head',
        'Waterfall', 'Orion', 'Crab', 'Ring', 'Eagle', 'Horsehead',
        'Tarantula', 'Helix',
    ];

    protected static array $suffixes = [
        'Nebula', 'Cloud', 'Veil', 'Mist', 'Wraith', 'Shroud',
    ];

    public static function generateNebulaName(): string
    {
        return static::randomElement(static::$nebulae).' '.static::randomElement(static::$suffixes);
    }
}
