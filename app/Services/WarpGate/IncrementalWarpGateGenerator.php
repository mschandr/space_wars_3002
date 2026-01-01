<?php

namespace App\Services\WarpGate;

use App\Enums\PointsOfInterest\PointOfInterestType;
use App\Models\Galaxy;
use App\Models\PointOfInterest;
use App\Models\WarpGate;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class IncrementalWarpGateGenerator
{
    private float $adjacencyThreshold;
    private float $hiddenGatePercentage;
    private int $maxGatesPerSystem;
    private ?Command $command;
    private int $gatesCreated = 0;
    private int $gatesSkipped = 0;

    public function __construct(
        float $adjacencyThreshold = 1.5,
        float $hiddenGatePercentage = 0.02,
        int $maxGatesPerSystem = 6,
        ?Command $command = null
    ) {
        $this->adjacencyThreshold = $adjacencyThreshold;
        $this->hiddenGatePercentage = $hiddenGatePercentage;
        $this->maxGatesPerSystem = $maxGatesPerSystem;
        $this->command = $command;
    }

    /**
     * Generate warp gates incrementally, processing one star at a time
     */
    public function generateGatesIncremental(Galaxy $galaxy): array
    {
        $this->gatesCreated = 0;
        $this->gatesSkipped = 0;

        // Get total star count for progress tracking
        $totalStars = $galaxy->pointsOfInterest()
            ->where('type', PointOfInterestType::STAR)
            ->count();

        if ($totalStars < 2) {
            return [
                'gates_created' => 0,
                'gates_skipped' => 0,
                'stars_processed' => 0,
            ];
        }

        $this->output("Processing {$totalStars} star systems...");
        $this->output("Adjacency threshold: {$this->adjacencyThreshold}");
        $this->output("Max gates per system: {$this->maxGatesPerSystem}");
        $this->newLine();

        // Process stars in chunks to avoid memory issues
        $chunkSize = 50;
        $starsProcessed = 0;

        PointOfInterest::where('galaxy_id', $galaxy->id)
            ->where('type', PointOfInterestType::STAR)
            ->orderBy('id')
            ->chunk($chunkSize, function ($starChunk) use (&$starsProcessed, $totalStars, $galaxy) {
                foreach ($starChunk as $star) {
                    $this->processStarSystem($star, $galaxy);
                    $starsProcessed++;

                    // Show progress every 10 stars
                    if ($starsProcessed % 10 === 0 || $starsProcessed === $totalStars) {
                        $percent = round(($starsProcessed / $totalStars) * 100, 1);
                        $this->output(
                            "Progress: {$starsProcessed}/{$totalStars} stars ({$percent}%) | " .
                            "Gates created: {$this->gatesCreated} | Skipped: {$this->gatesSkipped}",
                            true // Overwrite line
                        );
                    }
                }
            });

        $this->newLine();

        // Apply hidden gate percentage
        if ($this->hiddenGatePercentage > 0) {
            $this->applyHiddenGates($galaxy);
        }

        return [
            'gates_created' => $this->gatesCreated,
            'gates_skipped' => $this->gatesSkipped,
            'stars_processed' => $starsProcessed,
        ];
    }

    /**
     * Process a single star system, creating gates to nearby stars
     */
    private function processStarSystem(PointOfInterest $star, Galaxy $galaxy): void
    {
        // Get current connection count for this star
        $currentConnections = WarpGate::where('source_poi_id', $star->id)->count();

        // Skip if already at max connections
        if ($currentConnections >= $this->maxGatesPerSystem) {
            return;
        }

        // Find nearby stars within adjacency threshold
        $nearbyStars = $this->findNearbyStars($star, $galaxy);

        $connectionsToCreate = $this->maxGatesPerSystem - $currentConnections;
        $connectionsCreated = 0;

        foreach ($nearbyStars as $nearbyStarData) {
            if ($connectionsCreated >= $connectionsToCreate) {
                break;
            }

            $destinationId = $nearbyStarData['id'];

            // Check if bidirectional gate already exists
            if ($this->gateExists($star->id, $destinationId)) {
                $this->gatesSkipped++;
                continue;
            }

            // Check if destination star is at max connections
            $destinationConnections = WarpGate::where('source_poi_id', $destinationId)->count();
            if ($destinationConnections >= $this->maxGatesPerSystem) {
                $this->gatesSkipped++;
                continue;
            }

            // Create bidirectional gates
            $this->createBidirectionalGate(
                $galaxy->id,
                $star->id,
                $destinationId,
                $nearbyStarData['distance']
            );

            $connectionsCreated++;
            $this->gatesCreated += 2; // Bidirectional
        }
    }

    /**
     * Find nearby stars using efficient spatial query
     */
    private function findNearbyStars(PointOfInterest $star, Galaxy $galaxy): array
    {
        // Calculate bounding box for adjacency threshold
        $minX = $star->x - ($this->adjacencyThreshold * 10); // 10x threshold for redundancy
        $maxX = $star->x + ($this->adjacencyThreshold * 10);
        $minY = $star->y - ($this->adjacencyThreshold * 10);
        $maxY = $star->y + ($this->adjacencyThreshold * 10);

        // Query stars within bounding box
        $candidates = PointOfInterest::where('galaxy_id', $galaxy->id)
            ->where('type', PointOfInterestType::STAR)
            ->where('id', '!=', $star->id)
            ->whereBetween('x', [$minX, $maxX])
            ->whereBetween('y', [$minY, $maxY])
            ->get(['id', 'x', 'y']);

        // Calculate exact distances and sort
        $nearbyStars = [];
        foreach ($candidates as $candidate) {
            $distance = $this->calculateDistance($star, $candidate);

            // Only include stars within reasonable distance
            if ($distance <= $this->adjacencyThreshold * 10) {
                $nearbyStars[] = [
                    'id' => $candidate->id,
                    'distance' => $distance,
                ];
            }
        }

        // Sort by distance and return closest ones
        usort($nearbyStars, fn($a, $b) => $a['distance'] <=> $b['distance']);

        return array_slice($nearbyStars, 0, $this->maxGatesPerSystem * 2);
    }

    /**
     * Check if a bidirectional gate already exists
     */
    private function gateExists(int $sourceId, int $destinationId): bool
    {
        return WarpGate::where(function ($query) use ($sourceId, $destinationId) {
            $query->where('source_poi_id', $sourceId)
                  ->where('destination_poi_id', $destinationId);
        })->orWhere(function ($query) use ($sourceId, $destinationId) {
            $query->where('source_poi_id', $destinationId)
                  ->where('destination_poi_id', $sourceId);
        })->exists();
    }

    /**
     * Create bidirectional warp gates
     */
    private function createBidirectionalGate(
        int $galaxyId,
        int $sourceId,
        int $destinationId,
        float $distance
    ): void {
        // Calculate fuel cost based on distance
        $fuelCost = max(1, (int)ceil($distance / 2));

        // Create gate: source -> destination
        WarpGate::create([
            'uuid' => Str::uuid(),
            'galaxy_id' => $galaxyId,
            'source_poi_id' => $sourceId,
            'destination_poi_id' => $destinationId,
            'fuel_cost' => $fuelCost,
            'distance' => $distance,
            'is_hidden' => false,
            'status' => 'active',
        ]);

        // Create gate: destination -> source
        WarpGate::create([
            'uuid' => Str::uuid(),
            'galaxy_id' => $galaxyId,
            'source_poi_id' => $destinationId,
            'destination_poi_id' => $sourceId,
            'fuel_cost' => $fuelCost,
            'distance' => $distance,
            'is_hidden' => false,
            'status' => 'active',
        ]);
    }

    /**
     * Apply hidden gate percentage to existing gates
     */
    private function applyHiddenGates(Galaxy $galaxy): void
    {
        $totalGates = WarpGate::where('galaxy_id', $galaxy->id)->count();
        $hiddenCount = (int)ceil($totalGates * $this->hiddenGatePercentage);

        if ($hiddenCount > 0) {
            $this->output("Marking {$hiddenCount} gates as hidden ({$this->hiddenGatePercentage}%)...");

            WarpGate::where('galaxy_id', $galaxy->id)
                ->inRandomOrder()
                ->limit($hiddenCount)
                ->update(['is_hidden' => true]);
        }
    }

    /**
     * Calculate Euclidean distance between two POIs
     */
    private function calculateDistance(PointOfInterest $poi1, object $poi2): float
    {
        $dx = $poi2->x - $poi1->x;
        $dy = $poi2->y - $poi1->y;

        return sqrt($dx * $dx + $dy * $dy);
    }

    /**
     * Output message to console if command is available
     */
    private function output(string $message, bool $overwrite = false): void
    {
        if ($this->command) {
            if ($overwrite) {
                $this->command->getOutput()->write("\r\033[K" . $message);
            } else {
                $this->command->line($message);
            }
        }
    }

    /**
     * Output newline
     */
    private function newLine(): void
    {
        if ($this->command) {
            $this->command->newLine();
        }
    }
}
