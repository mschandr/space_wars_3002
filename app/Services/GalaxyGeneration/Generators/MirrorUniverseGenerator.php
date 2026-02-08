<?php

namespace App\Services\GalaxyGeneration\Generators;

use App\Enums\Galaxy\GalaxyStatus;
use App\Enums\PointsOfInterest\PointOfInterestType;
use App\Enums\WarpGate\GateType;
use App\Models\Galaxy;
use App\Models\WarpGate;
use App\Services\GalaxyGeneration\Contracts\GeneratorInterface;
use App\Services\GalaxyGeneration\Data\GenerationConfig;
use App\Services\GalaxyGeneration\Data\GenerationMetrics;
use App\Services\GalaxyGeneration\Data\GenerationResult;
use App\Services\MirrorUniverseService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Generates a mirror universe paired with the prime galaxy.
 *
 * The mirror universe is a high-risk, high-reward parallel dimension with:
 * - 2x resource spawns
 * - 1.5x trading prices
 * - 3x rare mineral spawn rates
 * - 2x pirate difficulty
 *
 * This generator:
 * 1. Creates the mirror galaxy record with same seed
 * 2. Populates the mirror with POIs, sectors, gates, hubs
 * 3. Updates the precursor gate to link prime -> mirror
 * 4. Creates return gate in mirror -> prime
 */
final class MirrorUniverseGenerator implements GeneratorInterface
{
    private MirrorUniverseService $mirrorService;

    public function __construct()
    {
        $this->mirrorService = app(MirrorUniverseService::class);
    }

    public function getName(): string
    {
        return 'mirror_universe';
    }

    public function getDependencies(): array
    {
        return [
            StarFieldGenerator::class,
            SectorGridGenerator::class,
            WarpGateNetworkGenerator::class,
            TradingInfrastructureGenerator::class,
            PrecursorContentGenerator::class,
        ];
    }

    public function generate(Galaxy $galaxy, array $context = []): GenerationResult
    {
        $metrics = new GenerationMetrics;

        // Check if mirror creation is enabled via config
        /** @var GenerationConfig|null $config */
        $config = $context['config'] ?? null;
        if ($config && ! $config->includeMirror) {
            $metrics->setCustom('skipped', 'Mirror universe disabled via config');

            return GenerationResult::success($metrics, ['mirror_galaxy_id' => null]);
        }

        // Fallback check if no config (shouldn't happen, but be safe)
        if (! config('game_config.mirror_universe.enabled', true)) {
            $metrics->setCustom('skipped', 'Mirror universe disabled in game config');

            return GenerationResult::success($metrics, ['mirror_galaxy_id' => null]);
        }

        // Check if galaxy already has a mirror
        if ($this->mirrorService->hasMirrorGalaxy($galaxy)) {
            $existingMirror = $galaxy->getPairedGalaxy();
            $metrics->setCustom('skipped', 'Mirror already exists');

            return GenerationResult::success($metrics, [
                'mirror_galaxy_id' => $existingMirror?->id,
            ]);
        }

        try {
            // Step 1: Create mirror galaxy record
            Log::info('Creating mirror galaxy record');
            $mirrorGalaxy = $this->mirrorService->createMirrorGalaxy($galaxy);
            $metrics->setCount('mirror_galaxy_created', 1);

            // Step 2: Generate POIs in mirror (same count as prime)
            $starCount = $galaxy->pointsOfInterest()
                ->where('type', PointOfInterestType::STAR)
                ->count();

            Log::info("Generating {$starCount} stars in mirror galaxy");
            Artisan::call('galaxy:expand', [
                'galaxy' => $mirrorGalaxy->id,
                '--stars' => $starCount,
            ]);
            $metrics->setCount('mirror_stars', $starCount);
            $this->freeMemory();

            // Step 3: Generate sectors directly (avoid Artisan command to ensure correct galaxy_id)
            Log::info('Generating sectors in mirror galaxy', ['mirror_galaxy_id' => $mirrorGalaxy->id]);
            $sectorResult = $this->generateSectorsForMirror($mirrorGalaxy, $config);
            $metrics->setCount('mirror_sectors', $sectorResult['sectors_created']);
            $this->freeMemory();

            // Step 4: Designate inhabited systems
            $inhabitedPercentage = config('game_config.galaxy.inhabited_percentage', 0.40);
            Artisan::call('galaxy:designate-inhabited', [
                'galaxy' => $mirrorGalaxy->id,
                '--percentage' => $inhabitedPercentage,
            ]);
            $this->freeMemory();

            // Step 5: Generate warp gates (denser network in mirror)
            Log::info('Generating warp gates in mirror galaxy');
            $adjacencyThreshold = max($mirrorGalaxy->width, $mirrorGalaxy->height) / 15;
            Artisan::call('galaxy:generate-gates', [
                'galaxy' => $mirrorGalaxy->id,
                '--adjacency' => $adjacencyThreshold,
                '--hidden-percentage' => 0.05,
                '--max-gates' => 8,
                '--regenerate' => true,
                '--incremental' => true,
            ]);
            $this->freeMemory();

            // Step 6: Generate trading hubs
            Log::info('Generating trading hubs in mirror galaxy');
            Artisan::call('trading:generate-hubs', [
                'galaxy' => $mirrorGalaxy->id,
            ]);
            Log::info('Trading hubs generation complete', ['memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 1)]);
            $this->freeMemory();

            // Step 7: Distribute pirates with boosted difficulty
            Log::info('Distributing pirates in mirror galaxy', ['memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 1)]);
            try {
                Artisan::call('galaxy:distribute-pirates', [
                    'galaxy' => $mirrorGalaxy->id,
                ]);
                Log::info('Pirates distribution complete', ['memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 1)]);
            } catch (\Throwable $e) {
                Log::error('Pirates distribution failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
                throw $e;
            }
            $this->freeMemory();

            // Step 8: Link the precursor gate to mirror
            Log::info('Linking precursor gate to mirror', ['memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 1)]);
            $this->linkPrecursorGate($galaxy, $mirrorGalaxy, $metrics);
            Log::info('Precursor gate linked', ['memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 1)]);

            // Mark mirror galaxy as active
            Log::info('Marking mirror galaxy as active', ['memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 1)]);
            $mirrorGalaxy->status = GalaxyStatus::ACTIVE;
            $mirrorGalaxy->generation_completed_at = now();
            $mirrorGalaxy->save();
            Log::info('Mirror universe generation complete', ['mirror_galaxy_id' => $mirrorGalaxy->id]);

            return GenerationResult::success($metrics, [
                'mirror_galaxy_id' => $mirrorGalaxy->id,
                'mirror_galaxy_uuid' => $mirrorGalaxy->uuid,
                'mirror_galaxy_name' => $mirrorGalaxy->name,
            ]);

        } catch (\Throwable $e) {
            Log::error('Mirror universe generation failed', [
                'galaxy_id' => $galaxy->id,
                'error' => $e->getMessage(),
            ]);

            return GenerationResult::failure($metrics, $e->getMessage());
        }
    }

    /**
     * Link the precursor gate (created by PrecursorContentGenerator) to the mirror galaxy.
     */
    private function linkPrecursorGate(Galaxy $primeGalaxy, Galaxy $mirrorGalaxy, GenerationMetrics $metrics): void
    {
        // Find the precursor gate (self-referencing placeholder)
        $precursorGate = WarpGate::where('galaxy_id', $primeGalaxy->id)
            ->where('gate_type', 'mirror_portal')
            ->where('status', 'precursor')
            ->first();

        if (! $precursorGate) {
            Log::warning('No precursor gate found to link to mirror');
            $metrics->setCount('precursor_gate_linked', 0);

            // Create new entry gate if none exists
            $primePoi = $this->mirrorService->selectRandomGateLocation($primeGalaxy);
            if ($primePoi) {
                $gates = $this->mirrorService->createMirrorGatePair($primeGalaxy, $mirrorGalaxy, $primePoi);
                $metrics->setCount('mirror_entry_gate_created', 1);
                $metrics->setCount('mirror_return_gate_created', 1);
            }

            return;
        }

        // Find a destination POI in the mirror galaxy
        $mirrorPoi = $this->mirrorService->selectRandomGateLocation($mirrorGalaxy);

        if (! $mirrorPoi) {
            Log::error('No suitable POI in mirror galaxy for gate destination');

            return;
        }

        // Update the precursor gate to point to mirror
        $precursorGate->update([
            'destination_poi_id' => $mirrorPoi->id,
            'dest_x' => $mirrorPoi->x,
            'dest_y' => $mirrorPoi->y,
            'status' => 'active',
            'gate_type' => GateType::MIRROR_ENTRY->value,
        ]);
        $metrics->setCount('precursor_gate_linked', 1);

        // Create return gate in mirror galaxy
        WarpGate::create([
            'uuid' => \Illuminate\Support\Str::uuid(),
            'galaxy_id' => $mirrorGalaxy->id,
            'source_poi_id' => $mirrorPoi->id,
            'destination_poi_id' => $precursorGate->source_poi_id,
            'source_x' => $mirrorPoi->x,
            'source_y' => $mirrorPoi->y,
            'dest_x' => $precursorGate->source_x,
            'dest_y' => $precursorGate->source_y,
            'distance' => 0,
            'is_hidden' => false, // Return gate is always visible
            'status' => 'active',
            'gate_type' => GateType::MIRROR_RETURN->value,
        ]);
        $metrics->setCount('mirror_return_gate_created', 1);

        Log::info('Precursor gate linked to mirror universe', [
            'entry_gate_id' => $precursorGate->id,
            'mirror_poi_id' => $mirrorPoi->id,
        ]);
    }

    /**
     * Attempt to free memory by triggering garbage collection.
     */
    private function freeMemory(): void
    {
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }
    }

    /**
     * Generate sectors directly for the mirror galaxy.
     *
     * Uses direct DB insert to ensure correct galaxy_id is used,
     * avoiding potential issues with Artisan command argument resolution.
     *
     * @return array{sectors_created: int, pois_assigned: int}
     */
    private function generateSectorsForMirror(Galaxy $mirrorGalaxy, ?GenerationConfig $config): array
    {
        // Clean up any orphaned sectors from previous failed transactions
        // This handles the edge case where a previous attempt failed after inserting sectors
        // but before the transaction committed (auto-increment IDs may have advanced)
        $existingSectors = DB::table('sectors')
            ->where('galaxy_id', $mirrorGalaxy->id)
            ->count();

        if ($existingSectors > 0) {
            Log::warning('Found orphaned sectors for mirror galaxy, cleaning up', [
                'mirror_galaxy_id' => $mirrorGalaxy->id,
                'orphaned_count' => $existingSectors,
            ]);
            DB::table('sectors')->where('galaxy_id', $mirrorGalaxy->id)->delete();
        }

        $gridSize = $config?->getGridSize() ?? 5;
        $sectorWidth = $mirrorGalaxy->width / $gridSize;
        $sectorHeight = $mirrorGalaxy->height / $gridSize;

        $greekLetters = [
            'Alpha', 'Beta', 'Gamma', 'Delta', 'Epsilon', 'Zeta', 'Eta', 'Theta',
            'Iota', 'Kappa', 'Lambda', 'Mu', 'Nu', 'Xi', 'Omicron', 'Pi',
            'Rho', 'Sigma', 'Tau', 'Upsilon', 'Phi', 'Chi', 'Psi', 'Omega',
        ];

        $now = now()->format('Y-m-d H:i:s');
        $rows = [];

        for ($y = 0; $y < $gridSize; $y++) {
            $rowName = $greekLetters[$y % count($greekLetters)];
            if ($y >= count($greekLetters)) {
                $rowName .= '-'.(int) floor($y / count($greekLetters));
            }

            for ($x = 0; $x < $gridSize; $x++) {
                $rows[] = [
                    'uuid' => (string) Str::uuid(),
                    'galaxy_id' => $mirrorGalaxy->id, // Explicitly use mirror galaxy ID
                    'name' => "{$rowName}-".($x + 1),
                    'grid_x' => $x,
                    'grid_y' => $y,
                    'x_min' => $x * $sectorWidth,
                    'x_max' => ($x + 1) * $sectorWidth,
                    'y_min' => $y * $sectorHeight,
                    'y_max' => ($y + 1) * $sectorHeight,
                    'danger_level' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        // Single batch insert for sectors
        DB::table('sectors')->insert($rows);
        $sectorsCreated = count($rows);

        // Assign POIs to sectors using SQL JOIN
        $maxGridIndex = $gridSize - 1;
        $poisAssigned = DB::update('
            UPDATE points_of_interest poi
            SET sector_id = (
                SELECT s.id FROM sectors s
                WHERE s.galaxy_id = poi.galaxy_id
                AND s.grid_x = LEAST(FLOOR(poi.x / ?), ?)
                AND s.grid_y = LEAST(FLOOR(poi.y / ?), ?)
                LIMIT 1
            )
            WHERE poi.galaxy_id = ?
        ', [$sectorWidth, $maxGridIndex, $sectorHeight, $maxGridIndex, $mirrorGalaxy->id]);

        Log::info('Mirror galaxy sectors created', [
            'mirror_galaxy_id' => $mirrorGalaxy->id,
            'sectors_created' => $sectorsCreated,
            'pois_assigned' => $poisAssigned,
        ]);

        return [
            'sectors_created' => $sectorsCreated,
            'pois_assigned' => $poisAssigned,
        ];
    }
}
