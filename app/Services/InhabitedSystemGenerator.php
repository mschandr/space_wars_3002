<?php

namespace App\Services;

use App\Enums\PointsOfInterest\PointOfInterestType;
use App\Models\Galaxy;
use App\Models\PointOfInterest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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

        // OPTIMIZED: Batch update all selected systems at once
        if ($inhabited->isNotEmpty()) {
            $inhabitedIds = $inhabited->pluck('id')->toArray();
            PointOfInterest::whereIn('id', $inhabitedIds)->update(['is_inhabited' => true]);

            // Refresh the collection to reflect the updated values
            $inhabited->each(function ($poi) {
                $poi->is_inhabited = true;
            });
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
     * OPTIMIZED: Uses sampling for large datasets to avoid O(n²) calculations
     *
     * @return array Statistics array
     */
    public function getDistributionStats(Galaxy $galaxy): array
    {
        // Use single query for counts
        $stats = DB::table('points_of_interest')
            ->where('galaxy_id', $galaxy->id)
            ->where('type', PointOfInterestType::STAR)
            ->selectRaw('COUNT(*) as total_stars')
            ->selectRaw('SUM(CASE WHEN is_inhabited = 1 THEN 1 ELSE 0 END) as inhabited_stars')
            ->first();

        $totalStars = (int) $stats->total_stars;
        $inhabitedStars = (int) $stats->inhabited_stars;
        $uninhabitedStars = $totalStars - $inhabitedStars;

        $percentage = $totalStars > 0 ? ($inhabitedStars / $totalStars) * 100 : 0;

        // Calculate average distance using sampling for large datasets
        $avgDistance = 0;
        if ($inhabitedStars > 1) {
            // For large datasets, sample to avoid O(n²) complexity
            $sampleSize = min(100, $inhabitedStars);
            $inhabited = PointOfInterest::where('galaxy_id', $galaxy->id)
                ->stars()
                ->inhabited()
                ->inRandomOrder()
                ->limit($sampleSize)
                ->get(['id', 'x', 'y']);

            if ($inhabited->count() > 1) {
                $distances = [];
                $count = $inhabited->count();
                // Limit comparisons for efficiency
                $maxComparisons = min(500, ($count * ($count - 1)) / 2);
                $comparisons = 0;

                for ($i = 0; $i < $count && $comparisons < $maxComparisons; $i++) {
                    for ($j = $i + 1; $j < $count && $comparisons < $maxComparisons; $j++) {
                        $distances[] = $this->calculateDistance($inhabited[$i], $inhabited[$j]);
                        $comparisons++;
                    }
                }
                $avgDistance = count($distances) > 0 ? array_sum($distances) / count($distances) : 0;
            }
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
