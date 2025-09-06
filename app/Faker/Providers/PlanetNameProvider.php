<?php

namespace App\Faker\Providers;

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

    protected WeightedRandomGenerator $styleChooser;

    public function __construct()
    {
        $this->styleChooser = new WeightedRandomGenerator();
        $this->styleChooser->registerValues([
            'procedural' => 70,
            'catalog'    => 15,
            'myth'       => 15,
        ]);
    }

    public function planetName(): string
    {
        $style = $this->styleChooser->generate();

        return match ($style) {
            'procedural' => $this->proceduralName(),
            'catalog'    => $this->catalogName(),
            'myth'       => $this->mythName(),
        };
    }

    protected function proceduralName(): string
    {
        $parts = random_int(2, 4);
        $name = '';
        for ($i = 0; $i < $parts; $i++) {
            $name .= static::randomElement(static::$syllables);
        }
        return ucfirst($name) . static::randomElement(static::$suffixes);
    }

    protected function catalogName(): string
    {
        return 'HD-' . random_int(100, 9999) . ' ' . RomanNumerals::romanize(random_int(1, 20));
    }

    protected function mythName(): string
    {
        return static::randomElement(MythologicalNames::$names);
    }
}
