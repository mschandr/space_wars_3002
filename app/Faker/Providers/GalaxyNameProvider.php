<?php

namespace App\Faker\Providers;

use App\Faker\Common\GalaxyNames;
use App\Faker\Common\GalaxyVerbs;
use Faker\Provider\Base;

class GalaxyNameProvider extends Base
{
    public static function generateGalaxyName(): string
    {
        return trim(static::randomElement(GalaxyNames::$names) . " " .
            static::randomElement(GalaxyVerbs::$verbs));
    }
}
