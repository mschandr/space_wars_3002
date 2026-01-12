<?php

namespace App\Services\Trading;

use App\Models\Mineral;
use App\Models\PointOfInterest;
use App\Models\TradingHub;
use Illuminate\Support\Collection;

class ProximityPricingService
{
    /**
     * Calculate supply and demand levels based on proximity to mineral sources
     *
     * @return array [demand_level, supply_level, nearest_distance]
     */
    public static function calculateProximityBasedLevels(
        TradingHub $hub,
        Mineral $mineral,
        Collection $allPois
    ): array {
        $hubPoi = $hub->pointOfInterest;

        // Find POIs that produce this mineral
        $producingPois = MineralSourceMapper::getPoisProducingMineral($allPois, $mineral->symbol);

        if ($producingPois->isEmpty()) {
            // No sources for this mineral - very high demand, very low supply
            return [
                'demand_level' => 90,
                'supply_level' => 10,
                'nearest_distance' => null,
            ];
        }

        // Find the nearest source
        $nearestDistance = PHP_FLOAT_MAX;
        $nearestPoi = null;

        foreach ($producingPois as $poi) {
            $distance = self::calculateDistance($hubPoi, $poi);
            if ($distance < $nearestDistance) {
                $nearestDistance = $distance;
                $nearestPoi = $poi;
            }
        }

        // Calculate supply based on distance
        // Closer sources = higher supply = lower prices
        // Distance ranges: 0-2 (very close), 2-5 (close), 5-10 (far), 10+ (very far)
        $supply = self::calculateSupplyFromDistance($nearestDistance);

        // Calculate demand based on rarity and hub connectivity
        $demand = self::calculateDemandFromRarity($mineral, $hub);

        return [
            'demand_level' => $demand,
            'supply_level' => $supply,
            'nearest_distance' => $nearestDistance,
        ];
    }

    /**
     * Calculate supply level based on distance to nearest source
     * Closer = higher supply (0-100 scale)
     */
    private static function calculateSupplyFromDistance(float $distance): int
    {
        // Supply decreases as distance increases
        // Using an exponential decay function
        // Very close (0-1): 80-100 supply
        // Close (1-3): 60-80 supply
        // Medium (3-6): 40-60 supply
        // Far (6-10): 20-40 supply
        // Very far (10+): 0-20 supply

        if ($distance < 1.0) {
            return (int) (100 - ($distance * 20));
        } elseif ($distance < 3.0) {
            return (int) (80 - (($distance - 1) * 10));
        } elseif ($distance < 6.0) {
            return (int) (60 - (($distance - 3) * 6.67));
        } elseif ($distance < 10.0) {
            return (int) (40 - (($distance - 6) * 5));
        } else {
            return max(5, (int) (20 - (($distance - 10) * 1)));
        }
    }

    /**
     * Calculate demand level based on mineral rarity and hub characteristics
     */
    private static function calculateDemandFromRarity(Mineral $mineral, TradingHub $hub): int
    {
        // Base demand from rarity
        $baseDemand = match ($mineral->rarity->value) {
            'abundant' => 30,
            'common' => 40,
            'uncommon' => 50,
            'rare' => 60,
            'very_rare' => 70,
            'epic' => 80,
            'legendary' => 90,
            'mythic' => 95,
        };

        // Hub connectivity affects demand (more gates = more traffic = higher demand)
        // Premium hubs with lots of traffic have higher demand
        $connectivityBonus = match ($hub->type) {
            'premium' => 10,
            'major' => 5,
            'standard' => 0,
        };

        $totalDemand = $baseDemand + $connectivityBonus;

        // Add some randomness (±5) for variety
        $totalDemand += mt_rand(-5, 5);

        return max(10, min(100, $totalDemand));
    }

    /**
     * Calculate Euclidean distance between two POIs
     */
    private static function calculateDistance(PointOfInterest $poi1, PointOfInterest $poi2): float
    {
        $dx = $poi2->x - $poi1->x;
        $dy = $poi2->y - $poi1->y;

        return sqrt($dx * $dx + $dy * $dy);
    }

    /**
     * Calculate how much a hub should stock of a mineral based on proximity
     * Returns a multiplier (0.1 to 3.0)
     */
    public static function calculateStockingMultiplier(?float $nearestDistance): float
    {
        if ($nearestDistance === null) {
            return 0.1; // Very little stock if no source
        }

        // Closer sources = more stock
        if ($nearestDistance < 2.0) {
            return 3.0; // Triple stock for very close sources
        } elseif ($nearestDistance < 4.0) {
            return 2.0; // Double stock for close sources
        } elseif ($nearestDistance < 7.0) {
            return 1.0; // Normal stock for medium distance
        } elseif ($nearestDistance < 12.0) {
            return 0.5; // Half stock for far sources
        } else {
            return 0.2; // Very little stock for very far sources
        }
    }

    /**
     * Determine if a hub should stock a mineral based on proximity and hub type
     */
    public static function shouldStockMineral(
        TradingHub $hub,
        Mineral $mineral,
        ?float $nearestDistance = null
    ): bool {
        // Premium hubs stock everything
        if ($hub->type === 'premium') {
            return true;
        }

        // If no nearby source, probably shouldn't stock (except rare items)
        if ($nearestDistance === null || $nearestDistance > 15) {
            // Only stock if it's very valuable
            return in_array($mineral->rarity->value, ['legendary', 'mythic']) && mt_rand(1, 100) <= 20;
        }

        // Major hubs stock items within reasonable distance
        if ($hub->type === 'major') {
            if ($nearestDistance < 8) {
                return true;
            }

            // Still might stock rare items from farther away
            return in_array($mineral->rarity->value, ['rare', 'very_rare', 'epic', 'legendary', 'mythic'])
                && mt_rand(1, 100) <= 40;
        }

        // Standard hubs only stock nearby, common items
        if ($nearestDistance < 5) {
            return in_array($mineral->rarity->value, ['abundant', 'common', 'uncommon', 'rare']);
        }

        return false;
    }

    /**
     * Get a text description of the supply situation
     */
    public static function getSupplyDescription(int $supplyLevel): string
    {
        return match (true) {
            $supplyLevel >= 80 => 'Abundant local supply',
            $supplyLevel >= 60 => 'Good local availability',
            $supplyLevel >= 40 => 'Moderate supply',
            $supplyLevel >= 20 => 'Limited supply',
            default => 'Scarce - must be imported',
        };
    }
}
