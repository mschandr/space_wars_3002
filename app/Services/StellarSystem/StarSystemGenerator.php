<?php

namespace App\Services\StellarSystem;

use App\Enums\PointsOfInterest\PointOfInterestStatus;
use App\Enums\PointsOfInterest\PointOfInterestType;
use App\Enums\PointsOfInterest\StellarClassification;
use App\Faker\Providers\PlanetNameProvider;
use App\Models\PointOfInterest;
use Assert\AssertionFailedException;
use mschandr\WeightedRandom\Generator\WeightedRandomGenerator;
use Random\Randomizer;

/**
 * Generates complete star systems with planets, moons, and asteroid belts
 */
class StarSystemGenerator
{
    private Randomizer $randomizer;

    private MoonGenerator $moonGenerator;

    public function __construct(Randomizer $randomizer)
    {
        $this->randomizer = $randomizer;
        $this->moonGenerator = new MoonGenerator($randomizer);
    }

    /**
     * Generate a complete star system (classification, planets, moons, asteroids)
     *
     * @param  PointOfInterest  $star  The star to generate a system for
     *
     * @throws AssertionFailedException
     */
    public function generateStarSystem(PointOfInterest $star): void
    {
        // 1. Assign stellar classification
        $stellarClass = $this->assignStellarClassification();

        // 2. Determine temperature
        [$minTemp, $maxTemp] = $stellarClass->temperatureRange();
        $temperature = $this->randomizer->getInt($minTemp, $maxTemp);

        // 3. Store star attributes
        $star->attributes = [
            'stellar_class' => $stellarClass->value,
            'temperature' => $temperature,
            'has_planets' => false,
        ];
        $star->save();

        // 4. Decide if this star has planets (75% chance)
        $hasPlanets = $this->randomWeighted([true => 75, false => 25]);

        if (! $hasPlanets) {
            return; // Barren star system
        }

        // 5. Determine number of planets
        [$minPlanets, $maxPlanets] = $stellarClass->planetCountRange();
        $planetCount = $this->randomizer->getInt($minPlanets, $maxPlanets);

        if ($planetCount === 0) {
            return;
        }

        // 6. Check for Hot Jupiter (replaces normal planet distribution)
        $startIndex = 1;
        if ($this->randomizer->nextFloat() < $stellarClass->hotJupiterChance()) {
            $this->generateHotJupiter($star, $stellarClass);
            $planetCount--; // Hot Jupiter takes orbital slot 1
            $startIndex = 2;
        }

        // 7. Generate planets
        for ($i = $startIndex; $i <= ($startIndex + $planetCount - 1); $i++) {
            $this->generatePlanet($star, $stellarClass, $i, $startIndex + $planetCount - 1);
        }

        // 8. Maybe generate asteroid belt
        if ($this->randomizer->nextFloat() < $stellarClass->asteroidBeltChance()) {
            $this->generateAsteroidBelt($star, $startIndex + $planetCount);
        }

        // Update star to mark it has planets
        $star->attributes = array_merge($star->attributes, ['has_planets' => true]);
        $star->save();
    }

    /**
     * Assign a stellar classification using weighted random based on astronomical frequencies
     *
     * @throws AssertionFailedException
     */
    private function assignStellarClassification(): StellarClassification
    {
        $chooser = new WeightedRandomGenerator;
        $weights = [];

        foreach (StellarClassification::cases() as $class) {
            $weights[$class->value] = $class->weight();
        }

        $chooser->registerValues($weights);

        return StellarClassification::from($chooser->generate());
    }

    /**
     * Generate a Hot Jupiter (gas giant in very close orbit)
     *
     * @throws AssertionFailedException
     */
    private function generateHotJupiter(
        PointOfInterest $star,
        StellarClassification $stellarClass
    ): void {
        $planet = PointOfInterest::create([
            'galaxy_id' => $star->galaxy_id,
            'parent_poi_id' => $star->id,
            'orbital_index' => 1,
            'type' => PointOfInterestType::HOT_JUPITER,
            'status' => PointOfInterestStatus::DRAFT,
            'x' => $star->x,
            'y' => $star->y,
            'name' => $this->generatePlanetName($star),
            'attributes' => [
                'orbital_distance_au' => round($this->randomizer->nextFloat() * 0.1, 3),
                'mass_jupiter' => round(0.5 + $this->randomizer->nextFloat() * 2.5, 2),
            ],
            'is_hidden' => false,
        ]);

        // Hot Jupiters rarely have moons due to tidal forces
        // Skip moon generation
    }

    /**
     * Generate a planet at the given orbital position
     *
     * @throws AssertionFailedException
     */
    private function generatePlanet(
        PointOfInterest $star,
        StellarClassification $stellarClass,
        int $orbitalIndex,
        int $totalPlanets
    ): void {
        // Determine planet type based on orbital position
        $planetType = PlanetTypeSelector::selectPlanetType(
            $stellarClass,
            $orbitalIndex,
            $totalPlanets
        );

        // Calculate orbital distance (simplified exponential distribution)
        $orbitalDistance = $this->calculateOrbitalDistance($stellarClass, $orbitalIndex);

        $planet = PointOfInterest::create([
            'galaxy_id' => $star->galaxy_id,
            'parent_poi_id' => $star->id,
            'orbital_index' => $orbitalIndex,
            'type' => $planetType,
            'status' => PointOfInterestStatus::DRAFT,
            'x' => $star->x,
            'y' => $star->y,
            'name' => $this->generatePlanetName($star),
            'attributes' => [
                'orbital_distance_au' => $orbitalDistance,
            ],
            'is_hidden' => false,
        ]);

        // Generate moons for this planet
        $this->moonGenerator->generateMoons($planet, $planetType, $orbitalDistance);
    }

    /**
     * Calculate orbital distance using exponential distribution (Titius-Bode-like)
     */
    private function calculateOrbitalDistance(
        StellarClassification $stellarClass,
        int $orbitalIndex
    ): float {
        $baseDistance = $stellarClass->baseOrbitalDistance();

        // Exponential distribution: distance = base * 1.7^(index - 1)
        // This creates spacing similar to our solar system
        return round($baseDistance * pow(1.7, $orbitalIndex - 1), 2);
    }

    /**
     * Generate an asteroid belt
     */
    private function generateAsteroidBelt(PointOfInterest $star, int $orbitalIndex): void
    {
        PointOfInterest::create([
            'galaxy_id' => $star->galaxy_id,
            'parent_poi_id' => $star->id,
            'orbital_index' => $orbitalIndex,
            'type' => PointOfInterestType::ASTEROID_BELT,
            'status' => PointOfInterestStatus::DRAFT,
            'x' => $star->x,
            'y' => $star->y,
            'name' => $star->name.' Asteroid Belt',
            'attributes' => [],
            'is_hidden' => false,
        ]);
    }

    /**
     * Generate a procedural planet name
     */
    private function generatePlanetName(PointOfInterest $star): string
    {
        return PlanetNameProvider::generatePlanetName();
    }

    /**
     * Weighted random selection helper
     *
     * @param  array<mixed, int>  $weights  [value => weight]
     *
     * @throws AssertionFailedException
     */
    private function randomWeighted(array $weights): mixed
    {
        $chooser = new WeightedRandomGenerator;
        $chooser->registerValues($weights);

        return $chooser->generate();
    }
}
