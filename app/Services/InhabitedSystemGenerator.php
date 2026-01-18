<?php

namespace App\Services;

use App\Enums\PointsOfInterest\PointOfInterestType;
use App\Models\Galaxy;
use App\Models\PointOfInterest;
use Illuminate\Support\Collection;

/**
 * Inhabited System Generator
 *
 * Designates a percentage of star systems as inhabited and ensures
 * they are well-distributed across the galaxy with minimum spacing.
 */
class InhabitedSystemGenerator
{
    /**
     * Designate inhabited systems in a galaxy
     *
     * @param  Galaxy  $galaxy  The galaxy to designate systems in
     * @param  float  $percentage  Percentage of star systems to mark as inhabited (0.0-1.0)
     * @param  float|null  $minSpacing  Minimum distance between inhabited systems (null = auto-calculate)
     * @return Collection Collection of inhabited POIs
     */
    public function designateInhabitedSystems(
        Galaxy $galaxy,
        float $percentage = 0.15,
        ?float $minSpacing = null
    ): Collection {
        // Get all star systems in the galaxy
        $allStars = PointOfInterest::where('galaxy_id', $galaxy->id)
            ->where('type', PointOfInterestType::STAR)
            ->where('is_hidden', false)
            ->get();

        if ($allStars->isEmpty()) {
            return collect();
        }

        // Calculate target count
        $targetCount = (int) ceil($allStars->count() * $percentage);
        $targetCount = max(1, min($targetCount, $allStars->count())); // Ensure at least 1

        // Auto-calculate minimum spacing if not provided
        if ($minSpacing === null) {
            $minSpacing = config('game_config.galaxy.inhabited_min_spacing', 50);
        }

        // Distribute inhabited systems with minimum spacing
        $inhabited = $this->distributeInhabitedSystems($allStars, $targetCount, $minSpacing);

        // Mark the selected systems as inhabited
        foreach ($inhabited as $poi) {
            $poi->is_inhabited = true;
            $poi->save();
        }

        return $inhabited;
    }

    /**
     * Distribute inhabited systems ensuring minimum spacing
     *
     * Uses a greedy algorithm: randomly select a system, mark it inhabited,
     * then remove all systems within minSpacing from the pool.
     *
     * @param  Collection  $allStars  All star systems to choose from
     * @param  int  $targetCount  Number of inhabited systems desired
     * @param  float  $minSpacing  Minimum distance between inhabited systems
     * @return Collection Selected inhabited systems
     */
    public function distributeInhabitedSystems(
        Collection $allStars,
        int $targetCount,
        float $minSpacing
    ): Collection {
        $inhabited = collect();
        $available = $allStars->shuffle(); // Randomize selection order

        while ($inhabited->count() < $targetCount && $available->isNotEmpty()) {
            // Pick the first available system
            $selected = $available->shift();
            $inhabited->push($selected);

            // Remove all systems within minSpacing from available pool
            $available = $available->filter(function ($poi) use ($selected, $minSpacing) {
                $distance = $this->calculateDistance($selected, $poi);

                return $distance >= $minSpacing;
            })->values(); // Re-index
        }

        return $inhabited;
    }

    /**
     * Calculate Euclidean distance between two POIs
     *
     * @return float Distance between the two points
     */
    private function calculateDistance(PointOfInterest $poi1, PointOfInterest $poi2): float
    {
        return sqrt(
            pow($poi2->x - $poi1->x, 2) +
            pow($poi2->y - $poi1->y, 2)
        );
    }

    /**
     * Get statistics about inhabited system distribution
     *
     * @return array Statistics array
     */
    public function getDistributionStats(Galaxy $galaxy): array
    {
        $totalStars = PointOfInterest::where('galaxy_id', $galaxy->id)
            ->stars()
            ->count();

        $inhabitedStars = PointOfInterest::where('galaxy_id', $galaxy->id)
            ->stars()
            ->inhabited()
            ->count();

        $uninhabitedStars = $totalStars - $inhabitedStars;

        $percentage = $totalStars > 0 ? ($inhabitedStars / $totalStars) * 100 : 0;

        // Calculate average distance between inhabited systems
        $inhabited = PointOfInterest::where('galaxy_id', $galaxy->id)
            ->stars()
            ->inhabited()
            ->get();

        $avgDistance = 0;
        if ($inhabited->count() > 1) {
            $distances = [];
            for ($i = 0; $i < $inhabited->count(); $i++) {
                for ($j = $i + 1; $j < $inhabited->count(); $j++) {
                    $distances[] = $this->calculateDistance($inhabited[$i], $inhabited[$j]);
                }
            }
            $avgDistance = count($distances) > 0 ? array_sum($distances) / count($distances) : 0;
        }

        return [
            'total_stars' => $totalStars,
            'inhabited_stars' => $inhabitedStars,
            'uninhabited_stars' => $uninhabitedStars,
            'percentage_inhabited' => round($percentage, 2),
            'avg_distance_between_inhabited' => round($avgDistance, 2),
        ];
    }
}
