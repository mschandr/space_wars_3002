<?php

namespace App\Services\StellarSystem;

use App\Enums\PointsOfInterest\PointOfInterestType;
use App\Enums\PointsOfInterest\StellarClassification;
use Assert\AssertionFailedException;
use mschandr\WeightedRandom\Generator\WeightedRandomGenerator;

/**
 * Selects appropriate planet types based on stellar classification and orbital position
 */
class PlanetTypeSelector
{
    /**
     * Select a planet type based on star type and orbital position
     *
     * @param  StellarClassification  $stellarClass  The parent star's classification
     * @param  int  $orbitalIndex  The planet's position (1 = innermost)
     * @param  int  $totalPlanets  Total number of planets in the system
     *
     * @throws AssertionFailedException
     */
    public static function selectPlanetType(
        StellarClassification $stellarClass,
        int $orbitalIndex,
        int $totalPlanets
    ): PointOfInterestType {
        // Calculate normalized orbital distance (0 = inner, 1 = outer)
        $normalizedDistance = ($totalPlanets > 1)
            ? ($orbitalIndex - 1) / ($totalPlanets - 1)
            : 0.5;

        // Inner system (0 - 0.4): Rocky planets
        if ($normalizedDistance < 0.4) {
            return self::selectInnerPlanetType($stellarClass);
        }

        // Outer system (0.4 - 1.0): Gas/Ice giants
        return self::selectOuterPlanetType($stellarClass, $normalizedDistance);
    }

    /**
     * Select a rocky planet type for inner system
     *
     * @throws AssertionFailedException
     */
    private static function selectInnerPlanetType(StellarClassification $stellarClass): PointOfInterestType
    {
        $chooser = new WeightedRandomGenerator;

        $weights = match ($stellarClass) {
            StellarClassification::O, StellarClassification::B => [
                PointOfInterestType::LAVA->value => 60,      // Very hot, close to massive star
                PointOfInterestType::CHTHONIC->value => 30,  // Stripped planets
                PointOfInterestType::TERRESTRIAL->value => 10,
            ],
            StellarClassification::A, StellarClassification::F => [
                PointOfInterestType::TERRESTRIAL->value => 40,
                PointOfInterestType::LAVA->value => 30,
                PointOfInterestType::SUPER_EARTH->value => 20,
                PointOfInterestType::OCEAN->value => 10,
            ],
            StellarClassification::G => [
                PointOfInterestType::TERRESTRIAL->value => 45,  // Earth-like
                PointOfInterestType::SUPER_EARTH->value => 25,
                PointOfInterestType::OCEAN->value => 15,
                PointOfInterestType::LAVA->value => 15,
            ],
            StellarClassification::K => [
                PointOfInterestType::TERRESTRIAL->value => 50,
                PointOfInterestType::SUPER_EARTH->value => 30,
                PointOfInterestType::OCEAN->value => 20,
            ],
            StellarClassification::M => [
                PointOfInterestType::TERRESTRIAL->value => 60,
                PointOfInterestType::SUPER_EARTH->value => 25,
                PointOfInterestType::LAVA->value => 15,  // Tidal locking common
            ],
        };

        $chooser->registerValues($weights);

        return PointOfInterestType::from($chooser->generate());
    }

    /**
     * Select a gas/ice giant type for outer system
     *
     * @throws AssertionFailedException
     */
    private static function selectOuterPlanetType(
        StellarClassification $stellarClass,
        float $normalizedDistance
    ): PointOfInterestType {
        $chooser = new WeightedRandomGenerator;

        // Very outer system (> 0.7) favors ice giants
        if ($normalizedDistance > 0.7) {
            $chooser->registerValues([
                PointOfInterestType::ICE_GIANT->value => 60,
                PointOfInterestType::GAS_GIANT->value => 30,
                PointOfInterestType::DWARF_PLANET->value => 10,
            ]);
        } else {
            // Mid-outer system favors gas giants (like Jupiter, Saturn)
            $chooser->registerValues([
                PointOfInterestType::GAS_GIANT->value => 70,
                PointOfInterestType::ICE_GIANT->value => 25,
                PointOfInterestType::SUPER_EARTH->value => 5,  // Super-Earth in outer system
            ]);
        }

        return PointOfInterestType::from($chooser->generate());
    }
}
