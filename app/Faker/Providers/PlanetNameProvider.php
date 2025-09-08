<?php

namespace App\Faker\Providers;

use Assert\AssertionFailedException;
use Faker\Provider\Base;
use App\Faker\Common\MythologicalNames;
use App\Faker\Common\RomanNumerals;
use mschandr\WeightedRandom\WeightedRandomGenerator;

class PlanetNameProvider extends Base
{
    protected static array $syllables = [
        'zor','ath','vel','dra','mor','lek','quor','pho','xin','tal','gar','nov',
        'ser','ith','ran','ul','var','nyx','thar','kal','zorin','bel','crys','tov',
        'mera','ion','vex','lyr','kron','syl','ora','tre','magn','vor','zen','cai',
    ];

    protected static array $suffixes = [
        '', ' Prime', ' Major', ' Minor', ' Colony', ' Reach',
        ' Station', ' Outpost', ' Sanctuary', ' Belt',
    ];

    protected static WeightedRandomGenerator $styleChooser;

    /**
     * @throws \Assert\AssertionFailedException
     */
    public static function init()
    {
        self::$styleChooser = new WeightedRandomGenerator();
        self::$styleChooser->registerValues([
            'procedural' => 70,
            'catalog'    => 15,
            'myth'       => 15,
        ]);
    }

    /**
     * @throws AssertionFailedException
     */
    public static function planetName(): string
    {
        self::init();
        $style = self::$styleChooser->generate();

        return match ($style) {
            'procedural' => self::proceduralName(),
            'catalog'    => self::catalogName(),
            'myth'       => self::mythName(),
        };
    }

    protected static function proceduralName(): string
    {
        $parts = random_int(2, 4);
        $name = '';
        for ($i = 0; $i < $parts; $i++) {
            $name .= static::randomElement(static::$syllables);
        }
        return ucfirst($name) . static::randomElement(static::$suffixes);
    }

    protected static function catalogName(): string
    {
        return 'HD-' . random_int(100, 9999) . ' ' . RomanNumerals::romanize(random_int(1, 20));
    }

    protected static function mythName(): string
    {
        return static::randomElement(MythologicalNames::$names);
    }
}
