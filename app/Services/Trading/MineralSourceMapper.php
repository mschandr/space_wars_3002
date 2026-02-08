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
     * Get spawn probability multiplier based on mineral rarity.
     * Rarer minerals are much less likely to spawn.
     */
    public static function getRaritySpawnMultiplier(string $mineralSymbol): float
    {
        // Map mineral symbols to their rarity multipliers
        // These values determine how likely a mineral is to spawn relative to the base POI probability
        $rarityMultipliers = [
            // Abundant (95% of base)
            'H2O' => 0.95, 'C' => 0.95,
            // Common (80% of base)
            'Fe' => 0.80, 'SiO2' => 0.80, 'Ni' => 0.80,
            // Uncommon (50% of base)
            'Ti' => 0.50, 'Cu' => 0.50, 'Al' => 0.50, 'Li' => 0.50,
            // Rare (25% of base)
            'Pt' => 0.25, 'Au' => 0.25, 'Pd' => 0.25, 'Co' => 0.25,
            // Very Rare (10% of base)
            'Rh' => 0.10, 'Ir' => 0.10, 'Os' => 0.10, 'T' => 0.10,
            // Epic (5% of base)
            'AM' => 0.05, 'Nt' => 0.05, 'DMC' => 0.05,
            // Legendary (2% of base)
            'QF' => 0.02, 'EM' => 0.02, 'CP' => 0.02,
            // Mythic (0.5% of base)
            'SCF' => 0.005, 'ZPE' => 0.005, 'PE' => 0.005,
        ];

        return $rarityMultipliers[$mineralSymbol] ?? 0.5;
    }

    /**
     * Assign random mineral production to a POI based on its type.
     * Rarity affects spawn probability - rarer minerals are less likely to appear.
     * Returns array of mineral symbols this POI produces.
     */
    public static function assignMineralProduction(PointOfInterestType $poiType): array
    {
        $availableMinerals = self::getMineralsForPoiType($poiType);
        $baseProbability = self::getProductionProbability($poiType, '');

        $production = [];

        foreach ($availableMinerals as $mineralSymbol) {
            // Apply rarity multiplier - rare minerals are much less likely to spawn
            $rarityMultiplier = self::getRaritySpawnMultiplier($mineralSymbol);
            $finalProbability = $baseProbability * $rarityMultiplier;

            $random = mt_rand(1, 10000) / 10000; // Higher precision for rare minerals

            if ($random <= $finalProbability) {
                $production[] = $mineralSymbol;
            }
        }

        // Ensure at least one mineral is produced - weighted by rarity
        if (empty($production) && ! empty($availableMinerals)) {
            // Pick the most common mineral from the available list
            $production[] = self::pickWeightedMineral($availableMinerals);
        }

        return $production;
    }

    /**
     * Pick a mineral weighted by rarity (common minerals more likely).
     */
    private static function pickWeightedMineral(array $minerals): string
    {
        $weights = [];
        $totalWeight = 0;

        foreach ($minerals as $symbol) {
            $weight = self::getRaritySpawnMultiplier($symbol) * 100;
            $weights[$symbol] = $weight;
            $totalWeight += $weight;
        }

        $random = mt_rand(1, (int) $totalWeight);
        $cumulative = 0;

        foreach ($weights as $symbol => $weight) {
            $cumulative += $weight;
            if ($random <= $cumulative) {
                return $symbol;
            }
        }

        return $minerals[0]; // Fallback
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
