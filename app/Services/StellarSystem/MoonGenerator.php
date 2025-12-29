<?php

namespace App\Services\StellarSystem;

use App\Enums\PointsOfInterest\PointOfInterestStatus;
use App\Enums\PointsOfInterest\PointOfInterestType;
use App\Models\PointOfInterest;
use Assert\AssertionFailedException;
use mschandr\WeightedRandom\Generator\WeightedRandomGenerator;
use Random\Randomizer;

/**
 * Generates moons for planets based on planet type and orbital distance
 */
class MoonGenerator
{
    private Randomizer $randomizer;

    public function __construct(Randomizer $randomizer)
    {
        $this->randomizer = $randomizer;
    }

    /**
     * Generate moons for a planet based on its type and orbital distance
     *
     * @param  PointOfInterest  $planet  The planet to generate moons for
     * @param  PointOfInterestType  $planetType  Type of the planet
     * @param  float  $orbitalDistance  Distance from star in AU
     *
     * @throws AssertionFailedException
     */
    public function generateMoons(
        PointOfInterest $planet,
        PointOfInterestType $planetType,
        float $orbitalDistance
    ): void {
        $moonCount = $this->determineMoonCount($planetType, $orbitalDistance);

        if ($moonCount === 0) {
            return;
        }

        for ($i = 1; $i <= $moonCount; $i++) {
            $this->createMoon($planet, $i);
        }
    }

    /**
     * Determine how many moons a planet should have
     *
     * @throws AssertionFailedException
     */
    private function determineMoonCount(
        PointOfInterestType $planetType,
        float $orbitalDistance
    ): int {
        // Inner planets (< 2 AU) rarely have moons due to tidal forces
        if ($orbitalDistance < 2.0) {
            return match ($planetType) {
                PointOfInterestType::TERRESTRIAL,
                PointOfInterestType::SUPER_EARTH => $this->randomWeighted([0 => 80, 1 => 20]),
                PointOfInterestType::OCEAN => $this->randomWeighted([0 => 70, 1 => 25, 2 => 5]),
                default => 0,
            };
        }

        // Outer planets can have many moons
        return match ($planetType) {
            PointOfInterestType::GAS_GIANT =>
                $this->randomWeighted([
                    5 => 20,
                    10 => 30,
                    15 => 25,
                    20 => 15,
                    30 => 10,
                ]),
            PointOfInterestType::ICE_GIANT =>
                $this->randomWeighted([
                    2 => 25,
                    5 => 35,
                    8 => 25,
                    12 => 15,
                ]),
            PointOfInterestType::SUPER_EARTH,
            PointOfInterestType::TERRESTRIAL =>
                $this->randomWeighted([
                    0 => 40,
                    1 => 35,
                    2 => 20,
                    3 => 5,
                ]),
            default => 0,
        };
    }

    /**
     * Weighted random selection helper
     *
     * @param  array<int, int>  $weights  [value => weight]
     * @return int
     *
     * @throws AssertionFailedException
     */
    private function randomWeighted(array $weights): int
    {
        $chooser = new WeightedRandomGenerator;
        $chooser->registerValues($weights);

        return $chooser->generate();
    }

    /**
     * Create a moon orbiting the given planet
     */
    private function createMoon(PointOfInterest $planet, int $index): void
    {
        PointOfInterest::create([
            'galaxy_id' => $planet->galaxy_id,
            'parent_poi_id' => $planet->id,
            'orbital_index' => $index,
            'type' => PointOfInterestType::MOON,
            'status' => PointOfInterestStatus::DRAFT,
            'x' => $planet->x,
            'y' => $planet->y,
            'name' => $this->generateMoonName($planet, $index),
            'attributes' => [],
            'is_hidden' => false,
        ]);
    }

    /**
     * Generate a name for a moon using Roman numerals
     */
    private function generateMoonName(PointOfInterest $planet, int $index): string
    {
        $romanNumerals = [
            'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X',
            'XI', 'XII', 'XIII', 'XIV', 'XV', 'XVI', 'XVII', 'XVIII', 'XIX', 'XX',
            'XXI', 'XXII', 'XXIII', 'XXIV', 'XXV', 'XXVI', 'XXVII', 'XXVIII', 'XXIX', 'XXX',
        ];

        if ($index <= count($romanNumerals)) {
            return $planet->name.' Moon '.$romanNumerals[$index - 1];
        }

        // Fallback for planets with > 30 moons
        return $planet->name.' Moon '.$index;
    }
}
