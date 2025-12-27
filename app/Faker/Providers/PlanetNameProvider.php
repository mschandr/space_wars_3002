<?php

namespace App\Faker\Providers;

use App\Faker\Common\MythologicalNames;
use App\Faker\Common\RomanNumerals;
use Assert\AssertionFailedException;
use Faker\Provider\Base;
use mschandr\WeightedRandom\WeightedRandomGenerator;
use Random\RandomException;

class PlanetNameProvider extends Base
{
    protected static array $syllables = [
        'zor', 'ath', 'vel', 'dra', 'mor', 'lek', 'quor', 'pho', 'xin', 'tal', 'gar', 'nov',
        'ser', 'ith', 'ran', 'ul', 'var', 'nyx', 'thar', 'kal', 'zorin', 'bel', 'crys', 'tov',
        'mera', 'ion', 'vex', 'lyr', 'kron', 'syl', 'ora', 'tre', 'magn', 'vor', 'zen', 'cai',
    ];

    protected static array $suffixes = [
        '', ' Prime', ' Major', ' Minor', ' Colony', ' Reach',
        ' Station', ' Outpost', ' Sanctuary', ' Belt',
    ];

    protected static WeightedRandomGenerator $styleChooser;

    /**
     * @throws AssertionFailedException
     * @throws RandomException
     */
    public static function generatePlanetName(): string
    {
        $name_method = self::init();

        return match ($name_method) {
            'procedural' => self::proceduralName(),
            'catalog' => self::catalogName(),
            'myth' => self::mythName(),
        };
    }

    /**
     * @throws AssertionFailedException
     */
    public static function init(): string
    {
        self::$styleChooser = new WeightedRandomGenerator;
        self::$styleChooser->registerValues([
            'procedural' => 70,
            'catalog' => 15,
            'myth' => 15,
        ]);

        return self::$styleChooser->generate();

    }

    /**
     * @throws RandomException
     */
    protected static function proceduralName(): string
    {
        $parts = random_int(2, 4);
        $name = '';
        for ($i = 0; $i < $parts; $i++) {
            $name .= static::randomElement(static::$syllables);
        }

        return ucfirst($name).static::randomElement(static::$suffixes);
    }

    protected static function catalogName(): string
    {
        return 'HD-'.random_int(100, 9999).' '.RomanNumerals::romanize(random_int(1, 20));
    }

    protected static function mythName(): string
    {
        return static::randomElement(MythologicalNames::$names);
    }
}
