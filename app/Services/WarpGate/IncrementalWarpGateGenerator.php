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

    /**
     * Initialize the generator with parameters controlling gate creation behavior.
     *
     * @param float $adjacencyThreshold Distance threshold used to consider two stars adjacent.
     * @param float $hiddenGatePercentage Fraction (0..1) of created gates to mark as hidden.
     * @param int   $maxGatesPerSystem   Maximum number of gates to create for a single star system.
     * @param Command|null $command      Optional console command instance used for progress output.
     */
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
     * Generate warp gates between inhabited star systems in the given galaxy.
     *
     * Builds a spatial index of inhabited stars, collects unique canonical gate pairs between nearby stars up to the configured per-system limit, inserts gates in bulk with deduplication, and optionally marks a percentage of gates as hidden.
     *
     * @param Galaxy $galaxy The galaxy whose inhabited star systems will be processed.
     * @return array{
     *     gates_created: int,   // number of gates inserted
     *     gates_skipped: int,   // number of candidate gates ignored due to deduplication or insertion conflicts
     *     stars_processed: int  // number of inhabited stars examined
     * }
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
     * Create a grid-based spatial index that maps cell keys to stars for fast neighbor lookup.
     *
     * Cells are sized using adjacencyThreshold * 2; each returned key is "cellX,cellY".
     *
     * @param iterable|array $stars Iterable of objects (or arrays) with numeric `x` and `y` coordinates.
     * @return array<string, array> Map from grid cell key ("cellX,cellY") to an array of stars assigned to that cell.
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
     * Build a deduplicated list of valid warp-gate pairs for nearby inhabited stars.
     *
     * Uses the provided spatial index to find neighbours within the adjacency threshold,
     * limits connections per star to the configured maximum, and normalizes pair ordering
     * using canonical coordinates so each physical gate appears once.
     *
     * @param array $stars List of star objects (each must have `id`, `x`, and `y` properties).
     * @param array $spatialIndex Grid-based spatial index mapping cell keys ("cellX,cellY") to arrays of star objects.
     * @param Galaxy $galaxy The galaxy context (used for contextual decisions and logging).
     * @return array An array of associative arrays representing unique gate pairs. Each entry contains:
     *               - source_poi_id (int)
     *               - destination_poi_id (int)
     *               - source_x (int)
     *               - source_y (int)
     *               - dest_x (int)
     *               - dest_y (int)
     *               - distance (float)
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
     * Insert multiple warp gate records for a galaxy in bulk, using INSERT IGNORE to avoid duplicate database entries and update creation/skipped counters.
     *
     * @param Galaxy $galaxy The galaxy for which gates are created.
     * @param array $pairs Array of gate pair records. Each element must contain the keys: 'source_poi_id', 'destination_poi_id', 'source_x', 'source_y', 'dest_x', 'dest_y', and 'distance'.
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
         * Mark a random subset of this galaxy's warp gates as hidden based on the configured percentage.
         *
         * Calculates ceil(total_gates * hiddenGatePercentage) and sets `is_hidden = true` on that many
         * randomly selected warp gates for the provided galaxy. If the calculated count is zero, no changes are made.
         *
         * @param Galaxy $galaxy The galaxy whose warp gates will be considered.
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