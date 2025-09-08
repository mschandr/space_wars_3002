<?php

namespace App\Faker\Providers;

use Faker\Provider\Base;
use App\Faker\Common\MythologicalNames;
use App\Faker\Common\GreekLetters;
use App\Faker\Common\RomanNumerals;
use App\Faker\Common\StarCatalog;
use mschandr\WeightedRandom\WeightedRandomGenerator;


class StarNameProvider extends Base
{
    protected static WeightedRandomGenerator $stylePicker;

    public static function init()
    {
        self::$stylePicker = new WeightedRandomGenerator();

        // Register weighted probabilities
        self::$stylePicker->registerValues([
            'catalog'      => 3,  // 30% chance
            'fictional'    => 5,  // 50% chance
            'mythological' => 2,  // 20% chance
        ]);
        return self::$stylePicker->generate();

    }

    public static function generateStarName(): string
    {
        $type = self::init();

        switch ($type) {
            case 'catalog':
                return static::randomElement(StarCatalog::$stars) . " " .
                    static::randomElement(GreekLetters::$letters) . " " .
                    static::randomElement(RomanNumerals::$numerals);

            case 'fictional':
                $syllables = [
                    'zor', 'ath', 'vel', 'dra', 'mor', 'lek', 'quor',
                    'pho', 'xin', 'tal', 'gar', 'nov', 'ser', 'ith',
                    'ran', 'ul', 'var', 'nyx', 'thar'
                ];
                $parts     = mt_rand(2, 3);
                $name      = '';
                for ($i = 0; $i < $parts; $i++) {
                    $name .= static::randomElement($syllables);
                }
                return ucfirst($name);

            case 'mythological':
            default:
                return static::randomElement(MythologicalNames::$names);
        }
    }
}
