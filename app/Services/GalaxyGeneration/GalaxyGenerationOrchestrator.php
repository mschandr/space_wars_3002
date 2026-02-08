<?php

namespace App\Services\GalaxyGeneration;

use App\Enums\Galaxy\GalaxyDistributionMethod;
use App\Enums\Galaxy\GalaxyRandomEngine;
use App\Enums\Galaxy\GalaxySizeTier;
use App\Enums\Galaxy\GalaxyStatus;
use App\Models\Galaxy;
use App\Services\GalaxyGeneration\Contracts\GeneratorInterface;
use App\Services\GalaxyGeneration\Data\GenerationConfig;
use App\Services\GalaxyGeneration\Data\GenerationMetrics;
use App\Services\GalaxyGeneration\Data\GenerationResult;
use App\Services\GalaxyGeneration\Generators\DefenseNetworkGenerator;
use App\Services\GalaxyGeneration\Generators\MineralDepositGenerator;
use App\Services\GalaxyGeneration\Generators\MirrorUniverseGenerator;
use App\Services\GalaxyGeneration\Generators\PlanetarySystemGenerator;
use App\Services\GalaxyGeneration\Generators\PrecursorContentGenerator;
use App\Services\GalaxyGeneration\Generators\SectorGridGenerator;
use App\Services\GalaxyGeneration\Generators\StarFieldGenerator;
use App\Services\GalaxyGeneration\Generators\TradingInfrastructureGenerator;
use App\Services\GalaxyGeneration\Generators\WarpGateNetworkGenerator;
use Database\Seeders\MineralSeeder;
use Database\Seeders\PirateCaptainSeeder;
use Database\Seeders\PirateFactionSeeder;
use Database\Seeders\PlansSeeder;
use Database\Seeders\ShipTypesSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Orchestrates galaxy generation through a pipeline of generators.
 *
 * Each generator is independent, stateless, and returns metrics.
 * The orchestrator handles ordering, error recovery, and reporting.
 */
final class GalaxyGenerationOrchestrator
{
    /**
     * Generator pipeline in execution order.
     *
     * @var array<class-string<GeneratorInterface>>
     */
    private const GENERATOR_PIPELINE = [
        StarFieldGenerator::class,
        PlanetarySystemGenerator::class,
        SectorGridGenerator::class,        // Must run after POIs exist
        WarpGateNetworkGenerator::class,
        MineralDepositGenerator::class,
        DefenseNetworkGenerator::class,
        TradingInfrastructureGenerator::class,
        PrecursorContentGenerator::class,
        MirrorUniverseGenerator::class,    // Must run after PrecursorContentGenerator
    ];

    private array $results = [];

    private GenerationMetrics $totalMetrics;

    /**
     * Generate a complete galaxy.
     *
     * @param  GalaxySizeTier  $tier  Galaxy size tier
     * @param  array  $options  Additional options
     * @return array Complete result with galaxy and all metrics
     */
    public function generate(GalaxySizeTier $tier, array $options = []): array
    {
        // Increase memory limit for large galaxies
        $this->setMemoryLimitForTier($tier);

        $this->totalMetrics = new GenerationMetrics;
        $this->results = [];

        $config = GenerationConfig::fromTier($tier, $options);

        $galaxy = null;

        try {
            // Step 1: Seed prerequisites (global, idempotent) - no transaction needed
            $this->seedPrerequisites();

            // Step 2: Create galaxy record in its own transaction
            DB::beginTransaction();
            $galaxy = $this->createGalaxyRecord($config);
            DB::commit();

            $this->totalMetrics->setCustom('galaxy_id', $galaxy->id);
            $this->totalMetrics->setCustom('galaxy_uuid', $galaxy->uuid);

            // Step 3: Run generator pipeline - each generator commits its own work
            // This prevents lock wait timeouts on large galaxies
            $context = ['config' => $config];

            foreach (self::GENERATOR_PIPELINE as $generatorClass) {
                $result = $this->runGenerator($generatorClass, $galaxy, $context);

                if (! $result->success) {
                    throw new \RuntimeException(
                        "Generator {$generatorClass} failed: {$result->error}"
                    );
                }

                // Merge result data into context for next generator
                // but exclude large arrays that aren't needed downstream
                $resultData = $result->data;
                unset($resultData['moon_specs']); // Large array, not needed downstream
                $context = array_merge($context, $resultData);

                // Free memory after each generator
                unset($result, $resultData);
                $this->freeMemory();
            }

            // Step 4: Finalize galaxy
            $galaxy->status = GalaxyStatus::ACTIVE;
            $galaxy->generation_completed_at = now();
            $galaxy->save();

            $this->totalMetrics->complete();

            return $this->buildFinalResult($galaxy, $config, true);

        } catch (\Throwable $e) {
            // Clean up partial galaxy if creation failed
            if ($galaxy && $galaxy->exists) {
                Log::warning('Cleaning up failed galaxy generation', ['galaxy_id' => $galaxy->id]);
                $this->cleanupFailedGalaxy($galaxy);
            }

            Log::error('Galaxy generation failed', [
                'tier' => $tier->value,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->totalMetrics->complete();

            return $this->buildFinalResult(null, $config, false, $e->getMessage());
        }
    }

    /**
     * Run a single generator and record results.
     */
    private function runGenerator(string $generatorClass, Galaxy $galaxy, array $context): GenerationResult
    {
        /** @var GeneratorInterface $generator */
        $generator = new $generatorClass;

        Log::info("Running generator: {$generator->getName()}");

        $result = $generator->generate($galaxy, $context);

        $this->results[$generator->getName()] = $result->toArray();

        Log::info("Generator {$generator->getName()} completed", [
            'success' => $result->success,
            'elapsed_ms' => $result->metrics->getElapsedMs(),
            'counts' => $result->metrics->getCounts(),
        ]);

        return $result;
    }

    /**
     * Create the galaxy database record.
     */
    private function createGalaxyRecord(GenerationConfig $config): Galaxy
    {
        $dimensions = $config->getDimensions();

        return Galaxy::create([
            'uuid' => Str::uuid(),
            'name' => $config->name ?? Galaxy::generateUniqueName(),
            'width' => $dimensions['width'],
            'height' => $dimensions['height'],
            'seed' => random_int(1, 999999),
            'distribution_method' => GalaxyDistributionMethod::RANDOM_SCATTER,
            'engine' => GalaxyRandomEngine::MT19937,
            'status' => GalaxyStatus::DRAFT,
            'turn_limit' => 0,
            'is_public' => $config->gameMode === 'multiplayer',
            'game_mode' => $config->gameMode,
            'owner_user_id' => $config->ownerUserId,
            'size_tier' => $config->tier,
            'core_bounds' => $config->getCoreBounds(),
            'generation_started_at' => now(),
        ]);
    }

    /**
     * Seed global prerequisites (idempotent).
     */
    private function seedPrerequisites(): void
    {
        if (\App\Models\Mineral::count() === 0) {
            Artisan::call('db:seed', ['--class' => MineralSeeder::class]);
        }

        if (\App\Models\Ship::count() === 0) {
            $seeder = new ShipTypesSeeder;
            $seeder->run();
        }

        if (\App\Models\Plan::count() === 0) {
            Artisan::call('db:seed', ['--class' => PlansSeeder::class]);
        }

        if (\App\Models\PirateFaction::count() === 0) {
            $seeder = new PirateFactionSeeder;
            $seeder->run();
        }

        if (\App\Models\PirateCaptain::count() === 0) {
            Artisan::call('db:seed', ['--class' => PirateCaptainSeeder::class]);
        }
    }

    /**
     * Build the final result array.
     */
    private function buildFinalResult(
        ?Galaxy $galaxy,
        GenerationConfig $config,
        bool $success,
        ?string $error = null
    ): array {
        $result = [
            'success' => $success,
            'error' => $error,
            'config' => [
                'tier' => $config->tier->value,
                'game_mode' => $config->gameMode,
                'dimensions' => $config->getDimensions(),
                'star_counts' => $config->getStarCounts(),
            ],
            'metrics' => [
                'total_elapsed_ms' => $this->totalMetrics->getElapsedMs(),
                'total_elapsed_seconds' => $this->totalMetrics->getElapsedSeconds(),
                'generators' => $this->results,
            ],
        ];

        if ($galaxy) {
            $result['galaxy'] = [
                'id' => $galaxy->id,
                'uuid' => $galaxy->uuid,
                'name' => $galaxy->name,
                'status' => $galaxy->status->value,
            ];

            // Gather final statistics
            $result['statistics'] = $this->gatherStatistics($galaxy);
        }

        return $result;
    }

    /**
     * Gather final statistics for the generated galaxy.
     */
    private function gatherStatistics(Galaxy $galaxy): array
    {
        $stats = [
            'total_pois' => $galaxy->pointsOfInterest()->count(),
            'total_stars' => $galaxy->pointsOfInterest()->stars()->count(),
            'core_stars' => $galaxy->corePointsOfInterest()->stars()->count(),
            'outer_stars' => $galaxy->outerPointsOfInterest()->stars()->count(),
            'inhabited_systems' => $galaxy->pointsOfInterest()->inhabited()->count(),
            'fortified_systems' => $galaxy->pointsOfInterest()->where('is_fortified', true)->count(),
            'warp_gates' => $galaxy->warpGates()->count(),
            'active_gates' => $galaxy->warpGates()->where('status', 'active')->count(),
            'dormant_gates' => $galaxy->warpGates()->where('status', 'dormant')->count(),
            'trading_hubs' => $galaxy->tradingHubs()->count(),
            'sectors' => $galaxy->sectors()->count(),
        ];

        // Include mirror galaxy info if it exists
        $mirrorGalaxy = $galaxy->getPairedGalaxy();
        if ($mirrorGalaxy) {
            $stats['mirror_universe'] = [
                'id' => $mirrorGalaxy->id,
                'uuid' => $mirrorGalaxy->uuid,
                'name' => $mirrorGalaxy->name,
                'total_stars' => $mirrorGalaxy->pointsOfInterest()->stars()->count(),
                'warp_gates' => $mirrorGalaxy->warpGates()->count(),
                'trading_hubs' => $mirrorGalaxy->tradingHubs()->count(),
            ];
        }

        return $stats;
    }

    /**
     * Set memory limit based on galaxy tier.
     *
     * Large and massive galaxies require more memory for:
     * - Building thousands of star/planet rows
     * - Spatial index structures for warp gates (holds all stars)
     * - Context accumulation between generators
     * - UUID generation overhead
     */
    private function setMemoryLimitForTier(GalaxySizeTier $tier): void
    {
        // Memory limits account for mirror universe generation which doubles the workload
        $memoryLimit = match ($tier) {
            GalaxySizeTier::SMALL => '512M',
            GalaxySizeTier::MEDIUM => '1024M',
            GalaxySizeTier::LARGE => '1536M',
            GalaxySizeTier::MASSIVE => '2048M',
        };

        ini_set('memory_limit', $memoryLimit);

        Log::info("Set memory limit for {$tier->value} galaxy", ['limit' => $memoryLimit]);
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
     * Clean up a failed galaxy generation by removing all related data.
     *
     * Since generators commit their own work, we need to delete any
     * partial data that was inserted before the failure.
     */
    private function cleanupFailedGalaxy(Galaxy $galaxy): void
    {
        try {
            // Delete in order of dependencies (children before parents)
            // Use raw queries for performance on large datasets

            $galaxyId = $galaxy->id;

            // Delete warp gates
            DB::table('warp_gates')->where('galaxy_id', $galaxyId)->delete();

            // Delete trading hub inventories (via trading hubs)
            $hubIds = DB::table('trading_hubs')
                ->join('points_of_interest', 'trading_hubs.poi_id', '=', 'points_of_interest.id')
                ->where('points_of_interest.galaxy_id', $galaxyId)
                ->pluck('trading_hubs.id');

            if ($hubIds->isNotEmpty()) {
                DB::table('trading_hub_inventories')->whereIn('trading_hub_id', $hubIds)->delete();
                DB::table('salvage_yard_inventories')->whereIn('trading_hub_id', $hubIds)->delete();
                DB::table('trading_hubs')->whereIn('id', $hubIds)->delete();
            }

            // Delete stellar cartographers
            DB::table('stellar_cartographers')
                ->join('points_of_interest', 'stellar_cartographers.poi_id', '=', 'points_of_interest.id')
                ->where('points_of_interest.galaxy_id', $galaxyId)
                ->delete();

            // Delete sectors
            DB::table('sectors')->where('galaxy_id', $galaxyId)->delete();

            // Delete points of interest (will cascade to children via parent_poi_id)
            DB::table('points_of_interest')->where('galaxy_id', $galaxyId)->delete();

            // Delete the mirror galaxy if it exists
            $mirrorGalaxy = $galaxy->getPairedGalaxy();
            if ($mirrorGalaxy) {
                $this->cleanupFailedGalaxy($mirrorGalaxy);
            }

            // Finally delete the galaxy itself
            $galaxy->delete();

            Log::info('Cleaned up failed galaxy', ['galaxy_id' => $galaxyId]);

        } catch (\Throwable $e) {
            Log::error('Failed to clean up galaxy', [
                'galaxy_id' => $galaxy->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
