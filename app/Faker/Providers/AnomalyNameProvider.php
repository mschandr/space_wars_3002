<?php

namespace App\Faker\Providers;

use Assert\AssertionFailedException;
use Faker\Provider\Base;
use mschandr\WeightedRandom\WeightedRandomGenerator;
use Random\RandomException;

class AnomalyNameProvider extends Base
{
    protected static WeightedRandomGenerator $styleChooser;

    protected static array $nicknames = [
        'The Maw', 'The Shatterfield', 'The Burning Veil', 'The Silent Zone',
        'The Shadow Sea', 'The Red Drift', 'The Howling Void', 'The Glass Storm',
        'The Whispering Rift', 'The Phantom Expanse', 'The Endless Spiral',
        'The Bitter Horizon', 'The Fractured Depths', 'The Black Bloom',
        'The Cinder Wound', 'The Starlight Graveyard', 'The Frozen Wave',
    ];

    protected static array $scientific = [
        'Subspace Rift', 'Temporal Distortion', 'Gravitic Flux',
        'Neutrino Storm', 'Tachyon Node', 'Quantum Vortex',
        'Spatial Rupture', 'Chronometric Surge', 'Subspace Shear',
        'Magnetic Disturbance',
    ];

    /**
     * @return string
     * @throws AssertionFailedException
     * @throws RandomException
     */
    public static function generateAnomalyName(): string
    {
        $style = self::init();

        return match ($style) {
            'nickname'   => self::nicknameStyle(),
            'scientific' => self::scientificStyle(),
            'sector'     => self::sectorStyle(),
        };
    }

    /**
     * Initialize or reuse the style chooser.
     *
     * @throws AssertionFailedException
     */
    protected static function init(): string
    {
        if (!isset(self::$styleChooser)) {
            self::$styleChooser = new WeightedRandomGenerator();
            self::$styleChooser->registerValues([
                'nickname'   => 70,
                'scientific' => 20,
                'sector'     => 10,
            ]);
        }
        return self::$styleChooser->generate();
    }

    /**
     * @return string
     */
    protected static function nicknameStyle(): string
    {
        return static::randomElement(static::$nicknames);
    }

    /**
     * @return string
     */
    protected static function scientificStyle(): string
    {
        return static::randomElement(static::$scientific);
    }

    /**
     * @return string
     */
    protected static function sectorStyle(): string
    {
        $letter = chr(random_int(65, 90)); // A–Z
        $number = random_int(10, 999);
        return "Sector {$letter}-{$number} Disturbance";
    }
}
