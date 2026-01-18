<?php

namespace App\Services\Trading;

use App\Enums\PointsOfInterest\PointOfInterestType;
use Illuminate\Support\Collection;

class MineralSourceMapper
{
    /**
     * Map POI types to the minerals they can produce
     * Returns mineral symbols that this POI type can produce
     */
    public static function getMineralsForPoiType(PointOfInterestType $poiType): array
    {
        return match ($poiType) {
            // Stars - fusion materials and exotic energy particles
            PointOfInterestType::STAR => [
                'T', 'CP', 'QF', 'SCF',
            ],

            // Rocky/Terrestrial bodies - metals and basic materials
            PointOfInterestType::ROGUE_PLANET,
            PointOfInterestType::PLANET,
            PointOfInterestType::TERRESTRIAL,
            PointOfInterestType::SUPER_EARTH,
            PointOfInterestType::DWARF_PLANET => [
                'Fe', 'Ti', 'Ni', 'Al', 'Cu', 'C', 'SiO2', 'Pt', 'Au', 'Co', 'Li',
            ],

            // Lava/Chthonic - high-temperature metals
            PointOfInterestType::LAVA,
            PointOfInterestType::CHTHONIC => [
                'Fe', 'Ti', 'Ni', 'Au', 'Pt', 'Ir', 'Os', 'Rh',
            ],

            // Ocean worlds - water and lithium
            PointOfInterestType::OCEAN => [
                'H2O', 'Li', 'SiO2', 'C',
            ],

            // Moons - similar to rocky planets but smaller deposits
            PointOfInterestType::MOON => [
                'Fe', 'Ti', 'Ni', 'Al', 'SiO2', 'H2O',
            ],

            // Asteroids - concentrated metal deposits
            PointOfInterestType::ASTEROID,
            PointOfInterestType::ASTEROID_BELT => [
                'Fe', 'Ni', 'Pt', 'Au', 'Pd', 'Ir', 'Os', 'Rh', 'Co',
            ],

            // Comets - volatiles and ices
            PointOfInterestType::COMET => [
                'H2O', 'C', 'SiO2',
            ],

            // Gas giants - exotic gases and rare materials
            PointOfInterestType::GAS_GIANT,
            PointOfInterestType::HOT_JUPITER => [
                'T', 'C', 'AM',
            ],

            // Ice giants - frozen volatiles and exotic ices
            PointOfInterestType::ICE_GIANT => [
                'H2O', 'C', 'AM', 'DMC',
            ],

            // Nebulas - exotic particles and primordial matter
            PointOfInterestType::NEBULA => [
                'H2O', 'C', 'DMC', 'AM', 'EM', 'ZPE', 'PE',
            ],

            // Black holes - ultra-exotic materials
            PointOfInterestType::BLACK_HOLE,
            PointOfInterestType::SUPER_MASSIVE_BLACK_HOLE => [
                'Nt', 'EM', 'QF', 'CP', 'SCF', 'ZPE', 'PE',
            ],

            // Anomalies - unpredictable, anything rare
            PointOfInterestType::ANOMALY => [
                'Rh', 'Ir', 'Os', 'Pd', 'AM', 'DMC', 'CP', 'EM', 'QF', 'PE',
            ],
        };
    }

    /**
     * Get production probability for a mineral at a POI type
     * Higher value = more likely to produce this mineral
     */
    public static function getProductionProbability(PointOfInterestType $poiType, string $mineralSymbol): float
    {
        $produces = self::getMineralsForPoiType($poiType);

        if (! in_array($mineralSymbol, $produces)) {
            return 0.0; // Doesn't produce this mineral
        }

        // Base probability depends on POI type (how easy/reliable to mine)
        return match ($poiType) {
            // Reliable sources - easy to mine
            PointOfInterestType::ASTEROID,
            PointOfInterestType::ASTEROID_BELT,
            PointOfInterestType::MOON => 0.9,

            // Good sources - moderate difficulty
            PointOfInterestType::PLANET,
            PointOfInterestType::TERRESTRIAL,
            PointOfInterestType::ROGUE_PLANET,
            PointOfInterestType::DWARF_PLANET,
            PointOfInterestType::SUPER_EARTH,
            PointOfInterestType::OCEAN => 0.8,

            // Challenging sources
            PointOfInterestType::LAVA,
            PointOfInterestType::CHTHONIC,
            PointOfInterestType::COMET,
            PointOfInterestType::ICE_GIANT => 0.6,

            // Difficult sources
            PointOfInterestType::NEBULA,
            PointOfInterestType::GAS_GIANT,
            PointOfInterestType::HOT_JUPITER => 0.5,

            // Very difficult sources
            PointOfInterestType::STAR => 0.3,

            // Extremely difficult/unpredictable sources
            PointOfInterestType::ANOMALY => 0.25,
            PointOfInterestType::BLACK_HOLE,
            PointOfInterestType::SUPER_MASSIVE_BLACK_HOLE => 0.15,
        };
    }

    /**
     * Assign random mineral production to a POI based on its type
     * Returns array of mineral symbols this POI produces
     */
    public static function assignMineralProduction(PointOfInterestType $poiType): array
    {
        $availableMinerals = self::getMineralsForPoiType($poiType);
        $baseProbability = self::getProductionProbability($poiType, '');

        $production = [];

        foreach ($availableMinerals as $mineralSymbol) {
            // Each mineral has a chance to be produced by this POI
            // More common minerals are more likely
            $random = mt_rand(1, 100) / 100;

            if ($random <= $baseProbability) {
                $production[] = $mineralSymbol;
            }
        }

        // Ensure at least one mineral is produced
        if (empty($production) && ! empty($availableMinerals)) {
            $production[] = $availableMinerals[array_rand($availableMinerals)];
        }

        return $production;
    }

    /**
     * Get all POIs in a galaxy that produce a specific mineral
     */
    public static function getPoisProducingMineral(Collection $pois, string $mineralSymbol): Collection
    {
        return $pois->filter(function ($poi) use ($mineralSymbol) {
            $produces = $poi->attributes['produces'] ?? [];

            return in_array($mineralSymbol, $produces);
        });
    }
}
