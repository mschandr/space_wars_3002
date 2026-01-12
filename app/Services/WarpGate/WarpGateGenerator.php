<?php

namespace App\Services\WarpGate;

use App\Enums\PointsOfInterest\PointOfInterestType;
use App\Models\Galaxy;
use App\Models\PointOfInterest;
use App\Models\WarpGate;
use Illuminate\Support\Collection;

class WarpGateGenerator
{
    private float $adjacencyThreshold;

    private float $hiddenGatePercentage;

    private int $maxGatesPerSystem;

    public function __construct(
        float $adjacencyThreshold = 1.5,
        float $hiddenGatePercentage = 0.02,
        int $maxGatesPerSystem = 6
    ) {
        $this->adjacencyThreshold = $adjacencyThreshold;
        $this->hiddenGatePercentage = $hiddenGatePercentage;
        $this->maxGatesPerSystem = $maxGatesPerSystem;
    }

    /**
     * Generate warp gates for inhabited star systems only
     * Uninhabited systems remain isolated to encourage exploration and colonization
     */
    public function generateGates(Galaxy $galaxy): Collection
    {
        // Only generate gates for INHABITED star systems
        $stars = $galaxy->pointsOfInterest()
            ->where('type', PointOfInterestType::STAR)
            ->where('is_inhabited', true)  // Only inhabited systems get warp gates
            ->get();

        if ($stars->count() < 2) {
            return collect();
        }

        $gates = collect();

        // Build minimum spanning tree for basic connectivity
        $mstGates = $this->buildMinimumSpanningTree($stars, $galaxy->id);
        $gates = $gates->merge($mstGates);

        // Add redundant connections for realism and accessibility
        $redundantGates = $this->addRedundantConnections($stars, $galaxy, $gates);
        $gates = $gates->merge($redundantGates);

        // Mark 2% of gates as hidden
        $this->markHiddenGates($gates);

        // Save all gates
        foreach ($gates as $gate) {
            $gate->save();
        }

        return $gates;
    }

    /**
     * Calculate gate density multiplier based on distance from galactic center
     * Linear falloff: center = 2.0x, edge = 1.0x
     *
     * @param  PointOfInterest  $star  The star system
     * @param  Galaxy  $galaxy  The galaxy
     * @return float Multiplier between 1.0 (edge) and 2.0 (center)
     */
    private function calculateGateDensityMultiplier(PointOfInterest $star, Galaxy $galaxy): float
    {
        $centerX = $galaxy->width / 2.0;
        $centerY = $galaxy->height / 2.0;

        $dx = $star->x - $centerX;
        $dy = $star->y - $centerY;
        $distance = sqrt($dx * $dx + $dy * $dy);

        $maxDistance = sqrt($centerX * $centerX + $centerY * $centerY);
        $normalizedDistance = min(1.0, $distance / $maxDistance);

        return 2.0 - $normalizedDistance;
    }

    /**
     * Build a minimum spanning tree using Prim's algorithm
     * This ensures all star systems are connected
     */
    private function buildMinimumSpanningTree(Collection $stars, int $galaxyId): Collection
    {
        $gates = collect();
        $visited = collect();
        $unvisited = $stars->pluck('id')->all();

        // Start with a random star
        $currentId = $unvisited[array_rand($unvisited)];
        $visited->push($currentId);
        $unvisited = array_values(array_diff($unvisited, [$currentId]));

        while (! empty($unvisited)) {
            $shortestDistance = PHP_FLOAT_MAX;
            $closestPair = null;

            // Find the shortest edge from visited to unvisited
            foreach ($visited as $visitedId) {
                $visitedStar = $stars->firstWhere('id', $visitedId);

                foreach ($unvisited as $unvisitedId) {
                    $unvisitedStar = $stars->firstWhere('id', $unvisitedId);
                    $distance = $this->calculateDistance($visitedStar, $unvisitedStar);

                    if ($distance < $shortestDistance) {
                        $shortestDistance = $distance;
                        $closestPair = [$visitedStar, $unvisitedStar];
                    }
                }
            }

            if ($closestPair) {
                [$source, $destination] = $closestPair;

                // Create bidirectional gates
                $gates->push($this->createGate($galaxyId, $source, $destination));
                $gates->push($this->createGate($galaxyId, $destination, $source));

                $visited->push($destination->id);
                $unvisited = array_values(array_diff($unvisited, [$destination->id]));
            }
        }

        return $gates;
    }

    /**
     * Add additional redundant connections for network robustness
     * Uses gate density multiplier to add more connections at galactic center
     */
    private function addRedundantConnections(Collection $stars, Galaxy $galaxy, Collection $existingGates): Collection
    {
        $gates = collect();
        $connectionCount = [];

        // Count existing connections per star
        foreach ($stars as $star) {
            $connectionCount[$star->id] = $existingGates->filter(function ($gate) use ($star) {
                return $gate->source_poi_id === $star->id;
            })->count();
        }

        foreach ($stars as $sourceStar) {
            // Skip if this star already has max connections
            if ($connectionCount[$sourceStar->id] >= $this->maxGatesPerSystem) {
                continue;
            }

            // Calculate gate density multiplier for this star
            $densityMultiplier = $this->calculateGateDensityMultiplier($sourceStar, $galaxy);

            // Determine redundant connections: center (2.0x) = 4, edge (1.0x) = 2
            $redundantConnectionsToAdd = (int) round(2 * $densityMultiplier);

            // Find nearby stars that aren't already connected
            $nearbyStars = $this->findNearbyStars($sourceStar, $stars, $existingGates, $gates, $galaxy, $redundantConnectionsToAdd);

            foreach ($nearbyStars as $destinationStar) {
                if ($connectionCount[$sourceStar->id] >= $this->maxGatesPerSystem) {
                    break;
                }

                if (! isset($connectionCount[$destinationStar->id])) {
                    $connectionCount[$destinationStar->id] = 0;
                }

                if ($connectionCount[$destinationStar->id] >= $this->maxGatesPerSystem) {
                    continue;
                }

                // Create bidirectional gates
                $outgoing = $this->createGate($galaxy->id, $sourceStar, $destinationStar);
                $incoming = $this->createGate($galaxy->id, $destinationStar, $sourceStar);

                $gates->push($outgoing);
                $gates->push($incoming);

                $connectionCount[$sourceStar->id]++;
                $connectionCount[$destinationStar->id]++;
            }
        }

        return $gates;
    }

    /**
     * Find nearby stars that aren't already connected to the source
     */
    private function findNearbyStars(
        PointOfInterest $source,
        Collection $allStars,
        Collection $existingGates,
        Collection $newGates,
        Galaxy $galaxy,
        int $limit = 4
    ): Collection {
        $allGates = $existingGates->merge($newGates);
        $connectedIds = $allGates
            ->filter(fn ($gate) => $gate->source_poi_id === $source->id)
            ->pluck('destination_poi_id')
            ->all();

        return $allStars
            ->filter(function ($star) use ($source, $connectedIds) {
                if ($star->id === $source->id) {
                    return false;
                }
                if (in_array($star->id, $connectedIds)) {
                    return false;
                }

                $distance = $this->calculateDistance($source, $star);

                return $distance <= $this->adjacencyThreshold * 5; // Consider stars within 5x adjacency threshold
            })
            ->sortBy(fn ($star) => $this->calculateDistance($source, $star))
            ->take($limit); // Dynamic limit based on gate density multiplier
    }

    /**
     * Mark a percentage of gates as hidden
     */
    private function markHiddenGates(Collection $gates): void
    {
        $totalGates = $gates->count();
        $hiddenCount = (int) ceil($totalGates * $this->hiddenGatePercentage);

        if ($hiddenCount === 0) {
            return;
        }

        // Randomly select gates to mark as hidden
        $gates->random(min($hiddenCount, $totalGates))->each(function ($gate) {
            $gate->is_hidden = true;
        });
    }

    /**
     * Create a warp gate between two POIs
     */
    private function createGate(int $galaxyId, PointOfInterest $source, PointOfInterest $destination): WarpGate
    {
        $gate = new WarpGate([
            'galaxy_id' => $galaxyId,
            'source_poi_id' => $source->id,
            'destination_poi_id' => $destination->id,
            'is_hidden' => false,
            'status' => 'active',
        ]);

        $gate->distance = $this->calculateDistance($source, $destination);

        return $gate;
    }

    /**
     * Calculate Euclidean distance between two POIs
     */
    private function calculateDistance(PointOfInterest $poi1, PointOfInterest $poi2): float
    {
        $dx = $poi2->x - $poi1->x;
        $dy = $poi2->y - $poi1->y;

        return sqrt($dx * $dx + $dy * $dy);
    }

    /**
     * Check if two systems are adjacent (within threshold distance)
     */
    public function areAdjacent(PointOfInterest $poi1, PointOfInterest $poi2): bool
    {
        return $this->calculateDistance($poi1, $poi2) <= $this->adjacencyThreshold;
    }

    /**
     * Get all accessible destinations from a source POI
     * Includes both direct gate connections and adjacent systems
     */
    public function getAccessibleDestinations(PointOfInterest $source, Collection $allStars): Collection
    {
        $accessible = collect();

        // Add direct gate connections (non-hidden)
        $directConnections = $source->outgoingGates()
            ->where('is_hidden', false)
            ->where('status', 'active')
            ->with('destinationPoi')
            ->get()
            ->pluck('destinationPoi');

        $accessible = $accessible->merge($directConnections);

        // Add adjacent systems
        $adjacentSystems = $allStars->filter(function ($star) use ($source) {
            return $star->id !== $source->id && $this->areAdjacent($source, $star);
        });

        $accessible = $accessible->merge($adjacentSystems);

        return $accessible->unique('id');
    }
}
