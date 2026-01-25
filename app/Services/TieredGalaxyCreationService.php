<?php

namespace App\Services;

use App\Enums\Galaxy\GalaxyDistributionMethod;
use App\Enums\Galaxy\GalaxyRandomEngine;
use App\Enums\Galaxy\GalaxySizeTier;
use App\Enums\Galaxy\GalaxyStatus;
use App\Events\GalaxyCreationCompleted;
use App\Events\GalaxyCreationProgress;
use App\Jobs\CompleteTieredGalaxyCreationJob;
use App\Models\Galaxy;
use App\Services\WarpGate\IncrementalWarpGateGenerator;
use Database\Seeders\PirateFactionSeeder;
use Database\Seeders\PrecursorShipSeeder;
use Database\Seeders\ShipTypesSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Main orchestrator for tiered galaxy creation.
 *
 * Creates galaxies with a civilized core surrounded by frontier wilderness.
 */
class TieredGalaxyCreationService
{
    public function __construct(
        private CoreSystemGenerator $coreGenerator,
        private OuterSystemGenerator $outerGenerator,
        private NpcGenerationService $npcGenerator,
        private MarketEventGenerator $marketEventGenerator,
    ) {}

    /**
     * Create a tiered galaxy synchronously.
     *
     * @param  GalaxySizeTier  $tier  Size tier (small, medium, large)
     * @param  array  $options  Additional options
     * @return array Result with galaxy and statistics
     */
    public function createTieredGalaxy(GalaxySizeTier $tier, array $options = []): array
    {
        set_time_limit(600);  // 10 minutes max
        ini_set('max_execution_time', '600');

        $startTime = microtime(true);

        try {
            DB::beginTransaction();

            // Step 1: Create galaxy record (0%)
            $galaxy = $this->createGalaxyRecord($tier, $options);
            $this->updateProgress($galaxy, 1, 'Creating galaxy record', 0);

            // Seed prerequisites
            $this->seedPrerequisites($galaxy);

            // Step 2: Generate core region stars (10%)
            $this->updateProgress($galaxy, 2, 'Generating core region stars', 10);
            $coreSystems = $this->coreGenerator->generateCoreRegion(
                $galaxy,
                $tier->getCoreStars(),
                $tier->getCoreBoundsArray()
            );

            // Step 3: Deploy fortress defenses (20%)
            $this->updateProgress($galaxy, 3, 'Deploying fortress defenses', 20);
            $this->coreGenerator->deployFortressDefenses($coreSystems);

            // Step 4: Create trading posts (30%)
            $this->updateProgress($galaxy, 4, 'Creating trading posts', 30);
            $this->coreGenerator->createTradingPosts($coreSystems);

            // Step 5: Generate core warp gate network (40%)
            $this->updateProgress($galaxy, 5, 'Generating core warp gate network', 40);
            $this->generateCoreWarpGates($galaxy, $tier);

            // Step 6: Expand galaxy to full dimensions (45%)
            // (already done via core/outer generation, but update progress)
            $this->updateProgress($galaxy, 6, 'Expanding galaxy dimensions', 45);

            // Step 7: Generate outer frontier stars (55%)
            $this->updateProgress($galaxy, 7, 'Generating outer frontier stars', 55);
            $outerSystems = $this->outerGenerator->generateOuterRegion(
                $galaxy,
                $tier->getOuterStars(),
                $tier->getCoreBoundsArray()
            );

            // Step 8: Create outer planetary systems (65%)
            $this->updateProgress($galaxy, 8, 'Creating outer planetary systems', 65);
            $this->outerGenerator->generatePlanetarySystems($outerSystems);

            // Step 9: Populate mineral deposits (70%)
            $this->updateProgress($galaxy, 9, 'Populating mineral deposits', 70);
            $allOuterPois = $galaxy->outerPointsOfInterest()->get();
            $this->outerGenerator->populateMineralDeposits($allOuterPois);

            // Step 10: Place dormant gates (75%)
            $this->updateProgress($galaxy, 10, 'Placing dormant gates in outer region', 75);
            $this->outerGenerator->generateDormantGates($galaxy, $outerSystems);

            // Step 11: Place precursor ship (80%)
            if (! ($options['skip_precursors'] ?? false)) {
                $this->updateProgress($galaxy, 11, 'Placing precursor ship', 80);
                $seeder = app(PrecursorShipSeeder::class);
                $seeder->seedPrecursorShip($galaxy);
            }

            // Step 12: Place mirror universe gate (85%)
            $this->updateProgress($galaxy, 12, 'Placing mirror universe gate', 85);
            // Mirror gate will be placed in mirror creation step

            // Step 13: Generate mirror universe (95%)
            $mirrorGalaxy = null;
            if (! ($options['skip_mirror'] ?? false) && config('game_config.mirror_universe.enabled', true)) {
                $this->updateProgress($galaxy, 13, 'Generating mirror universe', 95);
                Artisan::call('galaxy:create-mirror', ['galaxy' => $galaxy->id]);
                $mirrorGalaxy = $galaxy->fresh()->getPairedGalaxy();

                if ($mirrorGalaxy && ! ($options['skip_precursors'] ?? false)) {
                    $seeder = app(PrecursorShipSeeder::class);
                    $seeder->seedPrecursorShip($mirrorGalaxy);
                }
            }

            // Generate NPCs if applicable
            $npcsCreated = [];
            $gameMode = $options['game_mode'] ?? 'multiplayer';
            $npcCount = $options['npc_count'] ?? 0;
            if ($gameMode === 'single_player' && $npcCount < 1) {
                $npcCount = 5;
            }
            if ($npcCount > 0 && in_array($gameMode, ['single_player', 'mixed'])) {
                $npcs = $this->npcGenerator->generateNpcs(
                    $galaxy,
                    $npcCount,
                    $options['npc_difficulty'] ?? 'medium'
                );
                $npcsCreated = $npcs->map(fn ($npc) => [
                    'uuid' => $npc->uuid,
                    'call_sign' => $npc->call_sign,
                ])->toArray();
            }

            // Step 14: Mark galaxy ACTIVE (100%)
            $this->updateProgress($galaxy, 14, 'Activating galaxy', 100, 'completed');
            $galaxy->status = GalaxyStatus::ACTIVE;
            $galaxy->generation_completed_at = now();
            $galaxy->save();

            DB::commit();

            // Fire completion event
            event(new GalaxyCreationCompleted($galaxy));

            $executionTime = round(microtime(true) - $startTime, 2);

            return [
                'success' => true,
                'galaxy' => [
                    'id' => $galaxy->id,
                    'uuid' => $galaxy->uuid,
                    'name' => $galaxy->name,
                    'size_tier' => $tier->value,
                    'width' => $galaxy->width,
                    'height' => $galaxy->height,
                    'core_bounds' => $galaxy->core_bounds,
                    'game_mode' => $galaxy->game_mode,
                    'status' => $galaxy->status->value,
                ],
                'statistics' => $this->gatherStatistics($galaxy),
                'mirror_galaxy' => $mirrorGalaxy ? [
                    'id' => $mirrorGalaxy->id,
                    'uuid' => $mirrorGalaxy->uuid,
                    'name' => $mirrorGalaxy->name,
                ] : null,
                'npcs' => $npcsCreated,
                'execution_time_seconds' => $executionTime,
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('TieredGalaxyCreation failed', [
                'tier' => $tier->value,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Create a tiered galaxy asynchronously.
     * Returns immediately with basic structure, processes heavy ops in background.
     *
     * @param  GalaxySizeTier  $tier  Size tier
     * @param  array  $options  Additional options
     * @return array Result with galaxy info and processing status
     */
    public function createTieredGalaxyAsync(GalaxySizeTier $tier, array $options = []): array
    {
        $startTime = microtime(true);

        try {
            // Step 1: Create galaxy record
            $galaxy = $this->createGalaxyRecord($tier, $options);
            $this->updateProgress($galaxy, 1, 'Creating galaxy record', 0);

            // Seed prerequisites
            $this->seedPrerequisites($galaxy);

            // Step 2: Generate core region stars (fast)
            $this->updateProgress($galaxy, 2, 'Generating core region stars', 10);
            $coreSystems = $this->coreGenerator->generateCoreRegion(
                $galaxy,
                $tier->getCoreStars(),
                $tier->getCoreBoundsArray()
            );

            // Mark as processing
            $galaxy->status = GalaxyStatus::PROCESSING;
            $galaxy->save();

            // Dispatch background job for heavy operations
            CompleteTieredGalaxyCreationJob::dispatch($galaxy->id, $tier, array_merge($options, [
                'core_systems_created' => $coreSystems->count(),
            ]));

            $executionTime = round(microtime(true) - $startTime, 2);

            return [
                'success' => true,
                'async' => true,
                'galaxy' => [
                    'id' => $galaxy->id,
                    'uuid' => $galaxy->uuid,
                    'name' => $galaxy->name,
                    'size_tier' => $tier->value,
                    'width' => $galaxy->width,
                    'height' => $galaxy->height,
                    'core_bounds' => $galaxy->core_bounds,
                    'game_mode' => $galaxy->game_mode,
                    'status' => 'processing',
                ],
                'message' => 'Galaxy structure created. Background processing started.',
                'pending_steps' => [
                    'Deploy fortress defenses',
                    'Create trading posts',
                    'Generate core warp gate network',
                    'Generate outer frontier stars',
                    'Create outer planetary systems',
                    'Populate mineral deposits',
                    'Place dormant gates',
                    'Place precursor ship',
                    'Generate mirror universe',
                    'Generate NPCs',
                ],
                'execution_time_seconds' => $executionTime,
            ];

        } catch (\Exception $e) {
            Log::error('TieredGalaxyCreation async failed', [
                'tier' => $tier->value,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Create the galaxy database record.
     */
    private function createGalaxyRecord(GalaxySizeTier $tier, array $options): Galaxy
    {
        $gameMode = $options['game_mode'] ?? 'multiplayer';
        $ownerUserId = $options['owner_user_id'] ?? null;

        return Galaxy::create([
            'uuid' => Str::uuid(),
            'name' => $options['name'] ?? Galaxy::generateUniqueName(),
            'width' => $tier->getOuterBounds(),
            'height' => $tier->getOuterBounds(),
            'seed' => random_int(1, 999999),
            'distribution_method' => GalaxyDistributionMethod::RANDOM_SCATTER,
            'engine' => GalaxyRandomEngine::MT19937,
            'status' => GalaxyStatus::DRAFT,
            'turn_limit' => 0,
            'is_public' => $gameMode === 'multiplayer',
            'game_mode' => $gameMode,
            'owner_user_id' => $ownerUserId,
            'size_tier' => $tier,
            'core_bounds' => $tier->getCoreBoundsArray(),
            'progress_status' => [],
            'generation_started_at' => now(),
        ]);
    }

    /**
     * Update progress status and broadcast event.
     */
    private function updateProgress(Galaxy $galaxy, int $step, string $name, int $percentage, string $status = 'running'): void
    {
        $galaxy->updateProgress($step, $name, $percentage, $status);

        try {
            event(new GalaxyCreationProgress($galaxy->id, $step, $percentage, $name));
        } catch (\Exception $e) {
            // Broadcasting may fail if not configured, that's OK
            Log::debug('Progress broadcast failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Generate warp gates for the core region.
     * Uses IncrementalWarpGateGenerator directly for performance.
     */
    private function generateCoreWarpGates(Galaxy $galaxy, GalaxySizeTier $tier): void
    {
        // Delete existing gates if any
        $galaxy->warpGates()->delete();

        // Use generator directly instead of Artisan command
        $generator = new IncrementalWarpGateGenerator(
            adjacencyThreshold: (float) $tier->getWarpGateAdjacency(),
            hiddenGatePercentage: 0.02,
            maxGatesPerSystem: 6,
        );

        $generator->generateGatesIncremental($galaxy);
    }

    /**
     * Seed prerequisites if not already present.
     */
    private function seedPrerequisites(Galaxy $galaxy): void
    {
        if (\App\Models\Mineral::count() === 0) {
            Artisan::call('db:seed', ['--class' => 'MineralSeeder']);
        }

        if (\App\Models\Ship::count() === 0) {
            $seeder = new ShipTypesSeeder;
            $seeder->run();
            $seeder->generateShips($galaxy);
        }

        if (\App\Models\Plan::count() === 0) {
            Artisan::call('db:seed', ['--class' => 'PlansSeeder']);
        }

        if (\App\Models\PirateFaction::count() === 0) {
            $seeder = new PirateFactionSeeder;
            $seeder->run();
            $seeder->generatePirateFactions($galaxy);
        }

        if (\App\Models\PirateCaptain::count() === 0) {
            Artisan::call('db:seed', ['--class' => 'PirateCaptainSeeder']);
        }
    }

    /**
     * Gather statistics about the created galaxy.
     */
    private function gatherStatistics(Galaxy $galaxy): array
    {
        $galaxy->refresh();

        return [
            'total_stars' => $galaxy->pointsOfInterest()->stars()->count(),
            'core_stars' => $galaxy->corePointsOfInterest()->stars()->count(),
            'outer_stars' => $galaxy->outerPointsOfInterest()->stars()->count(),
            'core_inhabited' => $galaxy->corePointsOfInterest()->inhabited()->count(),
            'outer_inhabited' => $galaxy->outerPointsOfInterest()->inhabited()->count(),
            'fortified_systems' => $galaxy->pointsOfInterest()->fortified()->count(),
            'total_pois' => $galaxy->pointsOfInterest()->count(),
            'warp_gates' => $galaxy->warpGates()->count(),
            'dormant_gates' => $galaxy->warpGates()->dormant()->count(),
            'trading_hubs' => $galaxy->tradingHubs()->count(),
            'sectors' => $galaxy->sectors()->count(),
        ];
    }
}
