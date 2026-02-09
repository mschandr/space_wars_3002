<?php

namespace App\Services\GalaxyGeneration\Generators;

use App\Enums\Galaxy\RegionType;
use App\Enums\PointsOfInterest\PointOfInterestType;
use App\Models\Galaxy;
use App\Models\PointOfInterest;
use App\Models\WarpGate;
use App\Services\GalaxyGeneration\Contracts\GeneratorInterface;
use App\Services\GalaxyGeneration\Data\GenerationConfig;
use App\Services\GalaxyGeneration\Data\GenerationMetrics;
use App\Services\GalaxyGeneration\Data\GenerationResult;
use App\Services\GalaxyGeneration\Support\BulkInserter;
use App\Services\GalaxyGeneration\Support\SpatialIndex;
use Illuminate\Support\Str;

/**
 * Generates warp gate network for inhabited systems.
 *
 * Core region: Dense active gate network
 * Outer region: Sparse dormant gates (require activation)
 *
 * Uses spatial indexing for O(n) performance.
 */
final class WarpGateNetworkGenerator implements GeneratorInterface
{
    private const HIDDEN_GATE_PERCENTAGE = 0.02;

    public function getName(): string
    {
        return 'warp_gate_network';
    }

    public function getDependencies(): array
    {
        return [StarFieldGenerator::class];
    }

    public function generate(Galaxy $galaxy, array $context = []): GenerationResult
    {
        $metrics = new GenerationMetrics;

        /** @var GenerationConfig $config */
        $config = $context['config'];
        $adjacencyThreshold = $config->getWarpGateAdjacency();

        // Generate core gates (active)
        $coreResult = $this->generateCoreGates($galaxy, $adjacencyThreshold, $metrics);

        // Generate outer gates (dormant)
        $outerResult = $this->generateOuterGates($galaxy, $metrics);

        // Apply hidden percentage to core gates
        $this->applyHiddenGates($galaxy, $metrics);

        return GenerationResult::success($metrics, [
            'core_gates' => $coreResult,
            'outer_gates' => $outerResult,
            'total_gates' => $metrics->getCount('total_gates_created'),
        ]);
    }

    /**
     * Generate active warp gates for core (inhabited) systems.
     */
    private function generateCoreGates(Galaxy $galaxy, float $adjacencyThreshold, GenerationMetrics $metrics): int
    {
        // Load inhabited stars as minimal arrays (id, x, y only)
        $stars = PointOfInterest::where('galaxy_id', $galaxy->id)
            ->where('type', PointOfInterestType::STAR)
            ->where('is_inhabited', true)
            ->select(['id', 'x', 'y'])
            ->get()
            ->map(fn ($s) => ['id' => $s->id, 'x' => (int) $s->x, 'y' => (int) $s->y])
            ->all();

        $starCount = count($stars);
        $metrics->setCount('core_stars', $starCount);

        if ($starCount < 2) {
            return 0;
        }

        // For small datasets, use simple O(n²) approach to avoid spatial index overhead
        if ($starCount <= 100) {
            return $this->generateGatesSimple($galaxy->id, $stars, $adjacencyThreshold, 'active', false, $metrics);
        }

        // Build spatial index for larger datasets
        $spatialIndex = SpatialIndex::build($stars, $adjacencyThreshold * 2);

        // Process all at once but with smaller result batches
        $totalInserted = 0;
        $now = now()->format('Y-m-d H:i:s');
        $globalSeen = [];

        // Collect all pairs first
        $gatePairs = $this->collectGatePairsWithGlobalDedup(
            $stars,
            $spatialIndex,
            $adjacencyThreshold,
            'active',
            false,
            $globalSeen
        );

        // Free stars and index memory before building rows
        unset($stars, $spatialIndex, $globalSeen);
        gc_collect_cycles();

        $metrics->setCount('core_pairs_found', count($gatePairs));

        // Insert in smaller chunks
        foreach (array_chunk($gatePairs, 200) as $chunk) {
            $rows = $this->buildGateRows($galaxy->id, $chunk, $now);
            $totalInserted += BulkInserter::insertOrIgnore('warp_gates', $rows, 200);
            unset($rows);
        }

        unset($gatePairs);
        $metrics->increment('total_gates_created', $totalInserted);

        return $totalInserted;
    }

    /**
     * Generate dormant warp gates for outer systems.
     */
    private function generateOuterGates(Galaxy $galaxy, GenerationMetrics $metrics): int
    {
        $maxDistance = config('game_config.tiered_galaxy.outer_gate_max_distance', 200);

        // Load outer stars as minimal arrays
        $stars = PointOfInterest::where('galaxy_id', $galaxy->id)
            ->where('type', PointOfInterestType::STAR)
            ->where('region', RegionType::OUTER)
            ->select(['id', 'x', 'y'])
            ->get()
            ->map(fn ($s) => ['id' => $s->id, 'x' => (int) $s->x, 'y' => (int) $s->y])
            ->all();

        $starCount = count($stars);
        $metrics->setCount('outer_stars', $starCount);

        if ($starCount < 2) {
            return 0;
        }

        // For small datasets, use simple approach
        if ($starCount <= 150) {
            return $this->generateGatesSimple($galaxy->id, $stars, $maxDistance, 'dormant', true, $metrics);
        }

        // Build spatial index for larger datasets
        $spatialIndex = SpatialIndex::build($stars, $maxDistance);

        // Collect all pairs
        $totalInserted = 0;
        $now = now()->format('Y-m-d H:i:s');
        $globalSeen = [];

        $gatePairs = $this->collectGatePairsWithGlobalDedup(
            $stars,
            $spatialIndex,
            $maxDistance,
            'dormant',
            true,
            $globalSeen
        );

        // Free memory before building rows
        unset($stars, $spatialIndex, $globalSeen);
        gc_collect_cycles();

        $metrics->setCount('outer_pairs_found', count($gatePairs));

        // Insert in smaller chunks
        foreach (array_chunk($gatePairs, 200) as $chunk) {
            $rows = $this->buildGateRows($galaxy->id, $chunk, $now);
            $totalInserted += BulkInserter::insertOrIgnore('warp_gates', $rows, 200);
            unset($rows);
        }

        unset($gatePairs);
        $metrics->increment('total_gates_created', $totalInserted);

        return $totalInserted;
    }

    /**
     * Simple O(n²) gate generation for small datasets.
     * Avoids spatial index overhead for fewer stars.
     */
    private function generateGatesSimple(
        int $galaxyId,
        array $stars,
        float $maxDistance,
        string $status,
        bool $hidden,
        GenerationMetrics $metrics
    ): int {
        $pairs = [];
        $seen = [];

        $starCount = count($stars);
        for ($i = 0; $i < $starCount; $i++) {
            $s1 = $stars[$i];

            for ($j = $i + 1; $j < $starCount; $j++) {
                $s2 = $stars[$j];

                $dx = $s2['x'] - $s1['x'];
                $dy = $s2['y'] - $s1['y'];
                $distance = sqrt($dx * $dx + $dy * $dy);

                if ($distance <= $maxDistance) {
                    $key = "{$s1['x']},{$s1['y']},{$s2['x']},{$s2['y']}";
                    if (! isset($seen[$key])) {
                        $seen[$key] = true;
                        $pairs[] = [
                            'source_poi_id' => $s1['id'],
                            'destination_poi_id' => $s2['id'],
                            'source_x' => $s1['x'],
                            'source_y' => $s1['y'],
                            'dest_x' => $s2['x'],
                            'dest_y' => $s2['y'],
                            'distance' => $distance,
                            'status' => $status,
                            'is_hidden' => $hidden,
                        ];
                    }
                }
            }
        }

        $metrics->setCount($status === 'active' ? 'core_pairs_found' : 'outer_pairs_found', count($pairs));

        if (empty($pairs)) {
            return 0;
        }

        $now = now()->format('Y-m-d H:i:s');
        $rows = $this->buildGateRows($galaxyId, $pairs, $now);
        $inserted = BulkInserter::insertOrIgnore('warp_gates', $rows, 200);

        $metrics->increment('total_gates_created', $inserted);

        return $inserted;
    }

    /**
     * Collect gate pairs using spatial index and canonical coordinates.
     * Uses string keys for reliable deduplication.
     * Works with both array and object star data.
     */
    private function collectGatePairs(
        array $stars,
        SpatialIndex $spatialIndex,
        float $maxDistance,
        string $status,
        bool $hidden
    ): array {
        $seen = [];

        return $this->collectGatePairsWithGlobalDedup(
            $stars,
            $spatialIndex,
            $maxDistance,
            $status,
            $hidden,
            $seen
        );
    }

    /**
     * Collect gate pairs with a global deduplication set.
     * Used for chunked processing to avoid duplicates across batches.
     */
    private function collectGatePairsWithGlobalDedup(
        array $stars,
        SpatialIndex $spatialIndex,
        float $maxDistance,
        string $status,
        bool $hidden,
        array &$globalSeen
    ): array {
        $pairs = [];

        foreach ($stars as $star) {
            // Support both array and object formats
            $sx = is_array($star) ? $star['x'] : (int) $star->x;
            $sy = is_array($star) ? $star['y'] : (int) $star->y;
            $starId = is_array($star) ? $star['id'] : $star->id;

            $neighbors = $spatialIndex->findNeighbors($sx, $sy, $maxDistance, $star);

            foreach ($neighbors as $neighborData) {
                $neighbor = $neighborData['item'];
                $nx = is_array($neighbor) ? $neighbor['x'] : (int) $neighbor->x;
                $ny = is_array($neighbor) ? $neighbor['y'] : (int) $neighbor->y;
                $neighborId = is_array($neighbor) ? $neighbor['id'] : $neighbor->id;

                // Canonical ordering: lower X first, then lower Y
                if ($sx < $nx || ($sx === $nx && $sy <= $ny)) {
                    $key = "$sx,$sy,$nx,$ny";
                } else {
                    $key = "$nx,$ny,$sx,$sy";
                }

                if (! isset($globalSeen[$key])) {
                    $globalSeen[$key] = true;
                    $pairs[] = [
                        'source_poi_id' => $starId,
                        'destination_poi_id' => $neighborId,
                        'source_x' => $sx,
                        'source_y' => $sy,
                        'dest_x' => $nx,
                        'dest_y' => $ny,
                        'distance' => $neighborData['distance'],
                        'status' => $status,
                        'is_hidden' => $hidden,
                    ];
                }
            }
        }

        return $pairs;
    }

    /**
     * Build database rows for gates (optimized).
     */
    private function buildGateRows(int $galaxyId, array $pairs, string $now): array
    {
        $rows = [];
        $dormantReqs = '{"type":"sensor_level","value":3,"description":"Requires sensor level 3 to activate."}';

        foreach ($pairs as $pair) {
            $row = [
                'uuid' => (string) Str::uuid(),
                'galaxy_id' => $galaxyId,
                'source_poi_id' => $pair['source_poi_id'],
                'destination_poi_id' => $pair['destination_poi_id'],
                'source_x' => $pair['source_x'],
                'source_y' => $pair['source_y'],
                'dest_x' => $pair['dest_x'],
                'dest_y' => $pair['dest_y'],
                'distance' => $pair['distance'],
                'is_hidden' => $pair['is_hidden'],
                'status' => $pair['status'],
                'gate_type' => 'standard',
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if ($pair['status'] === 'dormant') {
                $row['activation_requirements'] = $dormantReqs;
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Mark a percentage of core gates as hidden.
     */
    private function applyHiddenGates(Galaxy $galaxy, GenerationMetrics $metrics): void
    {
        $totalCoreGates = WarpGate::where('galaxy_id', $galaxy->id)
            ->where('status', 'active')
            ->count();

        $hiddenCount = (int) ceil($totalCoreGates * self::HIDDEN_GATE_PERCENTAGE);

        if ($hiddenCount > 0) {
            WarpGate::where('galaxy_id', $galaxy->id)
                ->where('status', 'active')
                ->where('is_hidden', false)
                ->inRandomOrder()
                ->limit($hiddenCount)
                ->update(['is_hidden' => true]);

            $metrics->setCount('gates_marked_hidden', $hiddenCount);
        }
    }
}
