<?php

namespace App\Services\WarpGate;

use App\Enums\PointsOfInterest\PointOfInterestType;
use App\Models\Galaxy;
use App\Models\WarpGate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class IncrementalWarpGateGenerator
{
    private float $adjacencyThreshold;

    private float $hiddenGatePercentage;

    private int $maxGatesPerSystem;

    private ?Command $command;

    private int $gatesCreated = 0;

    private int $gatesSkipped = 0;

    private Galaxy $galaxy;

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
     * Generate warp gates incrementally for inhabited star systems only.
     * Uses canonical coordinate ordering with bulk inserts for O(n) performance.
     * Uninhabited systems remain isolated to encourage exploration and colonization.
     */
    public function generateGatesIncremental(Galaxy $galaxy): array
    {
        $this->galaxy = $galaxy;
        $this->gatesCreated = 0;
        $this->gatesSkipped = 0;

        // Load all inhabited stars with coordinates
        $stars = $galaxy->pointsOfInterest()
            ->where('type', PointOfInterestType::STAR)
            ->where('is_inhabited', true)
            ->get(['id', 'x', 'y']);

        $totalStars = $stars->count();

        if ($totalStars < 2) {
            return [
                'gates_created' => 0,
                'gates_skipped' => 0,
                'stars_processed' => 0,
            ];
        }

        $this->output("Processing {$totalStars} inhabited star systems...");
        $this->output("Adjacency threshold: {$this->adjacencyThreshold}");
        $this->output("Max gates per system: {$this->maxGatesPerSystem}");
        $this->newLine();

        // Build spatial index for fast neighbor lookup
        $starIndex = $this->buildSpatialIndex($stars);

        // Collect all gate pairs using canonical coordinates
        $gatePairs = $this->collectGatePairs($stars, $starIndex, $galaxy);

        $this->output('Found '.count($gatePairs).' unique gate pairs to create...');

        // Bulk insert with INSERT IGNORE for automatic deduplication
        $this->bulkInsertGates($galaxy, $gatePairs);

        $this->newLine();

        // Apply hidden gate percentage
        if ($this->hiddenGatePercentage > 0) {
            $this->applyHiddenGates($galaxy);
        }

        return [
            'gates_created' => $this->gatesCreated,
            'gates_skipped' => $this->gatesSkipped,
            'stars_processed' => $totalStars,
        ];
    }

    /**
     * Build a spatial index for fast neighbor lookup.
     * Groups stars into grid cells based on adjacency threshold.
     */
    private function buildSpatialIndex($stars): array
    {
        $cellSize = $this->adjacencyThreshold * 2;
        $index = [];

        foreach ($stars as $star) {
            $cellX = (int) floor($star->x / $cellSize);
            $cellY = (int) floor($star->y / $cellSize);
            $key = "{$cellX},{$cellY}";

            if (! isset($index[$key])) {
                $index[$key] = [];
            }
            $index[$key][] = $star;
        }

        return $index;
    }

    /**
     * Collect all valid gate pairs using canonical coordinate ordering.
     * Uses spatial index for O(n) performance instead of O(n²).
     */
    private function collectGatePairs($stars, array $spatialIndex, Galaxy $galaxy): array
    {
        $pairs = [];
        $seen = [];  // Track canonical pairs we've already added
        $cellSize = $this->adjacencyThreshold * 2;
        $starsProcessed = 0;
        $totalStars = count($stars);

        foreach ($stars as $star) {
            $cellX = (int) floor($star->x / $cellSize);
            $cellY = (int) floor($star->y / $cellSize);

            // Check current cell and 8 neighboring cells
            $candidates = [];
            for ($dx = -1; $dx <= 1; $dx++) {
                for ($dy = -1; $dy <= 1; $dy++) {
                    $key = ($cellX + $dx).','.($cellY + $dy);
                    if (isset($spatialIndex[$key])) {
                        foreach ($spatialIndex[$key] as $candidate) {
                            if ($candidate->id !== $star->id) {
                                $candidates[] = $candidate;
                            }
                        }
                    }
                }
            }

            // Calculate distances and filter by adjacency threshold
            $nearby = [];
            foreach ($candidates as $candidate) {
                $dx = $candidate->x - $star->x;
                $dy = $candidate->y - $star->y;
                $distance = sqrt($dx * $dx + $dy * $dy);

                if ($distance <= $this->adjacencyThreshold) {
                    $nearby[] = ['poi' => $candidate, 'distance' => $distance];
                }
            }

            // Sort by distance and limit connections
            usort($nearby, fn ($a, $b) => $a['distance'] <=> $b['distance']);
            $nearby = array_slice($nearby, 0, $this->maxGatesPerSystem);

            // Create canonical pairs
            foreach ($nearby as $neighbor) {
                $coords = WarpGate::canonicalCoordinates(
                    (int) $star->x,
                    (int) $star->y,
                    (int) $neighbor['poi']->x,
                    (int) $neighbor['poi']->y
                );

                $key = "{$coords['source_x']},{$coords['source_y']},{$coords['dest_x']},{$coords['dest_y']}";

                if (! isset($seen[$key])) {
                    $seen[$key] = true;
                    $pairs[] = [
                        'source_poi_id' => $star->id,
                        'destination_poi_id' => $neighbor['poi']->id,
                        'source_x' => $coords['source_x'],
                        'source_y' => $coords['source_y'],
                        'dest_x' => $coords['dest_x'],
                        'dest_y' => $coords['dest_y'],
                        'distance' => $neighbor['distance'],
                    ];
                }
            }

            $starsProcessed++;
            if ($starsProcessed % 50 === 0 || $starsProcessed === $totalStars) {
                $percent = round(($starsProcessed / $totalStars) * 100, 1);
                $this->output(
                    "Scanning: {$starsProcessed}/{$totalStars} stars ({$percent}%) | Pairs found: ".count($pairs),
                    true
                );
            }
        }

        $this->newLine();

        return $pairs;
    }

    /**
     * Bulk insert gates using INSERT IGNORE for automatic deduplication.
     */
    private function bulkInsertGates(Galaxy $galaxy, array $pairs): void
    {
        $now = now();
        $chunkSize = 500;
        $chunks = array_chunk($pairs, $chunkSize);

        foreach ($chunks as $chunk) {
            $rows = [];

            foreach ($chunk as $pair) {
                $uuid = (string) Str::uuid();
                $fuelCost = max(1, (int) ceil($pair['distance'] / 2));

                $rows[] = [
                    'uuid' => $uuid,
                    'galaxy_id' => $galaxy->id,
                    'source_poi_id' => $pair['source_poi_id'],
                    'destination_poi_id' => $pair['destination_poi_id'],
                    'source_x' => $pair['source_x'],
                    'source_y' => $pair['source_y'],
                    'dest_x' => $pair['dest_x'],
                    'dest_y' => $pair['dest_y'],
                    'distance' => $pair['distance'],
                    'fuel_cost' => $fuelCost,
                    'is_hidden' => false,
                    'status' => 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            // Use insertOrIgnore for automatic deduplication via unique constraint
            $inserted = DB::table('warp_gates')->insertOrIgnore($rows);
            $this->gatesCreated += $inserted;
            $this->gatesSkipped += count($rows) - $inserted;
        }
    }

    /**
     * Apply hidden gate percentage to existing gates
     */
    private function applyHiddenGates(Galaxy $galaxy): void
    {
        $totalGates = WarpGate::where('galaxy_id', $galaxy->id)->count();
        $hiddenCount = (int) ceil($totalGates * $this->hiddenGatePercentage);

        if ($hiddenCount > 0) {
            $this->output("Marking {$hiddenCount} gates as hidden ({$this->hiddenGatePercentage}%)...");

            WarpGate::where('galaxy_id', $galaxy->id)
                ->inRandomOrder()
                ->limit($hiddenCount)
                ->update(['is_hidden' => true]);
        }
    }

    /**
     * Output message to console if command is available
     */
    private function output(string $message, bool $overwrite = false): void
    {
        if ($this->command) {
            if ($overwrite) {
                $this->command->getOutput()->write("\r\033[K".$message);
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
