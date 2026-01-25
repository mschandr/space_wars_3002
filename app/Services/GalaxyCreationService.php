<?php

namespace App\Services;

use App\Enums\Galaxy\GalaxyDistributionMethod;
use App\Enums\Galaxy\GalaxyRandomEngine;
use App\Enums\Galaxy\GalaxyStatus;
use App\Enums\PointsOfInterest\PointOfInterestType;
use App\Jobs\CompleteGalaxyCreationJob;
use App\Models\Galaxy;
use App\Models\MarketEvent;
use App\Models\Mineral;
use App\Models\PirateCaptain;
use App\Models\PirateFaction;
use App\Models\Plan;
use App\Models\Ship;
use App\Models\TradingHub;
use App\Models\WarpLanePirate;
use Database\Seeders\PirateFactionSeeder;
use Database\Seeders\PrecursorShipSeeder;
use Database\Seeders\ShipTypesSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GalaxyCreationService
{
    /**
     * Enable debug mode for detailed logging and timing information.
     * Set to true to include:
     * - Per-step execution times
     * - Memory usage tracking
     * - Artisan command output
     * - Detailed error traces
     * - SQL query counts per step
     */
    public const DEBUG_MODE = true;

    /**
     * Log Artisan command output when debugging
     */
    public const DEBUG_LOG_ARTISAN_OUTPUT = true;

    private NpcGenerationService $npcGenerationService;

    private MarketEventGenerator $marketEventGenerator;

    private array $debugLog = [];

    private int $initialQueryCount = 0;

    public function __construct(
        NpcGenerationService $npcGenerationService,
        MarketEventGenerator $marketEventGenerator
    ) {
        $this->npcGenerationService = $npcGenerationService;
        $this->marketEventGenerator = $marketEventGenerator;
    }

    /**
     * Create a complete, playable galaxy via API
     *
     * @param  array  $options  Galaxy creation options
     * @return array Result with galaxy and statistics
     */
    public function createGalaxy(array $options): array
    {
        // Galaxy creation is a long-running process - disable time limit
        set_time_limit(300); // 5 minutes
        ini_set('max_execution_time', '300');

        $startTime = microtime(true);
        $steps = [];

        // Initialize debug tracking
        if (self::DEBUG_MODE) {
            $this->debugLog = [];
            $this->initialQueryCount = $this->getQueryCount();
            $this->logDebug('init', 'Galaxy creation started', [
                'options' => $options,
                'memory_start' => $this->formatBytes(memory_get_usage(true)),
                'peak_memory_start' => $this->formatBytes(memory_get_peak_usage(true)),
            ]);
        }

        // Extract and validate options
        $name = $options['name'] ?? null;
        $width = $options['width'] ?? 300;
        $height = $options['height'] ?? 300;
        $stars = $options['stars'] ?? 1000;
        $gridSize = $options['grid_size'] ?? 10;
        $gameMode = $options['game_mode'] ?? 'multiplayer';
        $ownerUserId = $options['owner_user_id'] ?? null;
        $npcCount = $options['npc_count'] ?? 0;
        $npcDifficulty = $options['npc_difficulty'] ?? 'medium';
        $skipMirror = $options['skip_mirror'] ?? false;
        $skipPirates = $options['skip_pirates'] ?? false;
        $skipPrecursors = $options['skip_precursors'] ?? false;

        // Validate game mode and NPC count
        if ($gameMode === 'single_player' && $npcCount < 1) {
            $npcCount = 5; // Default NPCs for single player
        }

        DB::beginTransaction();
        try {
            // Step 1: Create Galaxy
            $stepTimer = $this->startStepTimer();
            $steps[] = ['step' => 1, 'name' => 'Creating Galaxy', 'status' => 'running'];
            $galaxy = $this->createGalaxyRecord($name, $width, $height, $gameMode, $ownerUserId);
            $steps[0] = $this->completeStep($steps[0], $stepTimer, ['galaxy_id' => $galaxy->id]);

            // Step 2: Seed Prerequisites
            $stepTimer = $this->startStepTimer();
            $steps[] = ['step' => 2, 'name' => 'Verifying Prerequisites', 'status' => 'running'];
            $this->seedPrerequisites($galaxy);
            $steps[1] = $this->completeStep($steps[1], $stepTimer);

            // Step 3: Generate Stars/POIs
            $stepTimer = $this->startStepTimer();
            $steps[] = ['step' => 3, 'name' => "Generating {$stars} Stars", 'status' => 'running'];
            $this->runArtisanCommand('galaxy:expand', [
                'galaxy' => $galaxy->id,
                '--stars' => $stars,
            ]);
            $steps[2] = $this->completeStep($steps[2], $stepTimer);

            // Step 4: Assign Mineral Production
            $stepTimer = $this->startStepTimer();
            $steps[] = ['step' => 4, 'name' => 'Assigning Mineral Production', 'status' => 'running'];
            $this->runArtisanCommand('trading:assign-production', [
                'galaxy' => $galaxy->id,
            ]);
            $steps[3] = $this->completeStep($steps[3], $stepTimer);

            // Step 5: Generate Sectors
            $stepTimer = $this->startStepTimer();
            $steps[] = ['step' => 5, 'name' => 'Generating Sector Grid', 'status' => 'running'];
            $this->runArtisanCommand('galaxy:generate-sectors', [
                'galaxy' => $galaxy->id,
                '--grid-size' => $gridSize,
            ]);
            $steps[4] = $this->completeStep($steps[4], $stepTimer);

            // Step 6: Designate Inhabited Systems
            $stepTimer = $this->startStepTimer();
            $steps[] = ['step' => 6, 'name' => 'Designating Inhabited Systems', 'status' => 'running'];
            $inhabitedPercentage = config('game_config.galaxy.inhabited_percentage', 0.40);
            $this->runArtisanCommand('galaxy:designate-inhabited', [
                'galaxy' => $galaxy->id,
                '--percentage' => $inhabitedPercentage,
            ]);
            $steps[5] = $this->completeStep($steps[5], $stepTimer);

            // Step 7: Generate Warp Gates
            $stepTimer = $this->startStepTimer();
            $steps[] = ['step' => 7, 'name' => 'Generating Warp Gate Network', 'status' => 'running'];
            $adjacencyThreshold = max($width, $height) / 15;
            $this->runArtisanCommand('galaxy:generate-gates', [
                'galaxy' => $galaxy->id,
                '--incremental' => true,
                '--regenerate' => true,
                '--adjacency' => $adjacencyThreshold,
            ]);
            $steps[6] = $this->completeStep($steps[6], $stepTimer);

            // Step 8: Generate Trading Hubs
            $stepTimer = $this->startStepTimer();
            $steps[] = ['step' => 8, 'name' => 'Generating Trading Hubs', 'status' => 'running'];
            $hubProbability = config('game_config.inhabited_systems.guaranteed_services.trading_hub', 0.65);
            $this->runArtisanCommand('trading:generate-hubs', [
                'galaxy' => $galaxy->id,
                '--min-gates' => 1,
                '--hub-probability' => $hubProbability,
                '--min-spacing' => 100,
            ]);
            $steps[7] = $this->completeStep($steps[7], $stepTimer);

            // Step 9: Populate Trading Hub Inventory
            $stepTimer = $this->startStepTimer();
            $steps[] = ['step' => 9, 'name' => 'Populating Trading Hub Inventory', 'status' => 'running'];
            $this->runArtisanCommand('trading-hub:populate-inventory', [
                'galaxy' => $galaxy->id,
                '--regenerate' => true,
            ]);
            $steps[8] = $this->completeStep($steps[8], $stepTimer);

            // Step 10: Generate Cartographer Shops
            $stepTimer = $this->startStepTimer();
            $steps[] = ['step' => 10, 'name' => 'Establishing Cartographer Shops', 'status' => 'running'];
            $spawnRate = config('game_config.star_charts.spawn_rate', 0.3);
            $this->runArtisanCommand('cartography:generate-shops', [
                'galaxy' => $galaxy->id,
                '--spawn-rate' => $spawnRate,
                '--regenerate' => true,
            ]);
            $steps[9] = $this->completeStep($steps[9], $stepTimer);

            // Step 11: Generate Market Events
            $stepTimer = $this->startStepTimer();
            $steps[] = ['step' => 11, 'name' => 'Generating Market Events', 'status' => 'running'];
            $this->generateMarketEvents();
            $steps[10] = $this->completeStep($steps[10], $stepTimer);

            // Step 12: Spawn Precursor Ship (optional)
            if (! $skipPrecursors) {
                $stepTimer = $this->startStepTimer();
                $steps[] = ['step' => 12, 'name' => 'Spawning Precursor Ship', 'status' => 'running'];
                $seeder = app(PrecursorShipSeeder::class);
                $seeder->seedPrecursorShip($galaxy);
                $steps[11] = $this->completeStep($steps[11], $stepTimer);
            }

            // Step 13: Distribute Pirates (optional)
            if (! $skipPirates) {
                $stepTimer = $this->startStepTimer();
                $stepIndex = count($steps);
                $steps[] = ['step' => $stepIndex + 1, 'name' => 'Distributing Pirates', 'status' => 'running'];
                $this->runArtisanCommand('galaxy:distribute-pirates', [
                    'galaxy' => $galaxy->id,
                ]);
                $steps[$stepIndex] = $this->completeStep($steps[$stepIndex], $stepTimer);
            }

            // Step 14: Create Mirror Universe (optional)
            $mirrorGalaxy = null;
            if (! $skipMirror && config('game_config.mirror_universe.enabled', true)) {
                $stepTimer = $this->startStepTimer();
                $stepIndex = count($steps);
                $steps[] = ['step' => $stepIndex + 1, 'name' => 'Creating Mirror Universe', 'status' => 'running'];
                $this->runArtisanCommand('galaxy:create-mirror', [
                    'galaxy' => $galaxy->id,
                ]);
                $steps[$stepIndex] = $this->completeStep($steps[$stepIndex], $stepTimer);

                // Spawn precursor in mirror
                $mirrorGalaxy = $galaxy->fresh()->getPairedGalaxy();
                if ($mirrorGalaxy && ! $skipPrecursors) {
                    $seeder = app(PrecursorShipSeeder::class);
                    $seeder->seedPrecursorShip($mirrorGalaxy);
                }
            }

            // Step 15: Generate NPCs (if applicable)
            $npcsCreated = [];
            if ($npcCount > 0 && in_array($gameMode, ['single_player', 'mixed'])) {
                $stepTimer = $this->startStepTimer();
                $stepIndex = count($steps);
                $steps[] = ['step' => $stepIndex + 1, 'name' => "Generating {$npcCount} NPC Players", 'status' => 'running'];

                $npcs = $this->npcGenerationService->generateNpcs(
                    $galaxy,
                    $npcCount,
                    $npcDifficulty
                );

                $npcsCreated = $npcs->map(fn ($npc) => [
                    'uuid' => $npc->uuid,
                    'call_sign' => $npc->call_sign,
                    'archetype' => $npc->archetype,
                    'difficulty' => $npc->difficulty,
                ])->toArray();

                $steps[$stepIndex] = $this->completeStep($steps[$stepIndex], $stepTimer, [
                    'npcs_created' => count($npcsCreated),
                ]);
            }

            // Update galaxy status to active
            $galaxy->status = GalaxyStatus::ACTIVE;
            $galaxy->save();

            DB::commit();

            // Gather statistics
            $statistics = $this->gatherStatistics($galaxy);
            $executionTime = round(microtime(true) - $startTime, 2);

            $result = [
                'success' => true,
                'galaxy' => [
                    'id' => $galaxy->id,
                    'uuid' => $galaxy->uuid,
                    'name' => $galaxy->name,
                    'width' => $galaxy->width,
                    'height' => $galaxy->height,
                    'game_mode' => $galaxy->game_mode,
                    'status' => $galaxy->status->value,
                ],
                'mirror_galaxy' => $mirrorGalaxy ? [
                    'id' => $mirrorGalaxy->id,
                    'uuid' => $mirrorGalaxy->uuid,
                    'name' => $mirrorGalaxy->name,
                ] : null,
                'npcs' => $npcsCreated,
                'statistics' => $statistics,
                'steps' => $steps,
                'execution_time_seconds' => $executionTime,
            ];

            // Add debug information if enabled
            if (self::DEBUG_MODE) {
                $result['debug'] = $this->getDebugSummary($startTime);
            }

            return $result;

        } catch (\Exception $e) {
            DB::rollBack();

            // Log detailed error in debug mode
            if (self::DEBUG_MODE) {
                $this->logDebug('error', 'Galaxy creation failed', [
                    'exception' => get_class($e),
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }

            throw $e;
        }
    }

    /**
     * Create a galaxy asynchronously - returns quickly with basic structure.
     *
     * This method creates the core galaxy structure synchronously (fast operations),
     * then dispatches heavy operations (inventory population, pirates, mirror, NPCs)
     * to a background job.
     *
     * @param  array  $options  Galaxy creation options
     * @return array Result with galaxy info and processing status
     */
    public function createGalaxyAsync(array $options): array
    {
        $startTime = microtime(true);
        $steps = [];

        // Extract options
        $name = $options['name'] ?? null;
        $width = $options['width'] ?? 300;
        $height = $options['height'] ?? 300;
        $stars = $options['stars'] ?? 1000;
        $gridSize = $options['grid_size'] ?? 10;
        $gameMode = $options['game_mode'] ?? 'multiplayer';
        $ownerUserId = $options['owner_user_id'] ?? null;
        $npcCount = $options['npc_count'] ?? 0;

        // Validate game mode and NPC count
        if ($gameMode === 'single_player' && $npcCount < 1) {
            $npcCount = 5;
        }

        try {
            // Step 1: Create Galaxy Record
            $stepTimer = $this->startStepTimer();
            $steps[] = ['step' => 1, 'name' => 'Creating Galaxy', 'status' => 'running'];
            $galaxy = $this->createGalaxyRecord($name, $width, $height, $gameMode, $ownerUserId);
            $steps[0] = $this->completeStep($steps[0], $stepTimer, ['galaxy_id' => $galaxy->id]);

            // Step 2: Seed Prerequisites
            $stepTimer = $this->startStepTimer();
            $steps[] = ['step' => 2, 'name' => 'Verifying Prerequisites', 'status' => 'running'];
            $this->seedPrerequisites($galaxy);
            $steps[1] = $this->completeStep($steps[1], $stepTimer);

            // Step 3: Generate Stars/POIs
            $stepTimer = $this->startStepTimer();
            $steps[] = ['step' => 3, 'name' => "Generating {$stars} Stars", 'status' => 'running'];
            $this->runArtisanCommand('galaxy:expand', [
                'galaxy' => $galaxy->id,
                '--stars' => $stars,
            ]);
            $steps[2] = $this->completeStep($steps[2], $stepTimer);

            // Step 4: Assign Mineral Production
            $stepTimer = $this->startStepTimer();
            $steps[] = ['step' => 4, 'name' => 'Assigning Mineral Production', 'status' => 'running'];
            $this->runArtisanCommand('trading:assign-production', [
                'galaxy' => $galaxy->id,
            ]);
            $steps[3] = $this->completeStep($steps[3], $stepTimer);

            // Step 5: Generate Sectors
            $stepTimer = $this->startStepTimer();
            $steps[] = ['step' => 5, 'name' => 'Generating Sector Grid', 'status' => 'running'];
            $this->runArtisanCommand('galaxy:generate-sectors', [
                'galaxy' => $galaxy->id,
                '--grid-size' => $gridSize,
            ]);
            $steps[4] = $this->completeStep($steps[4], $stepTimer);

            // Step 6: Designate Inhabited Systems
            $stepTimer = $this->startStepTimer();
            $steps[] = ['step' => 6, 'name' => 'Designating Inhabited Systems', 'status' => 'running'];
            $inhabitedPercentage = config('game_config.galaxy.inhabited_percentage', 0.40);
            $this->runArtisanCommand('galaxy:designate-inhabited', [
                'galaxy' => $galaxy->id,
                '--percentage' => $inhabitedPercentage,
            ]);
            $steps[5] = $this->completeStep($steps[5], $stepTimer);

            // Step 7: Generate Warp Gates
            $stepTimer = $this->startStepTimer();
            $steps[] = ['step' => 7, 'name' => 'Generating Warp Gate Network', 'status' => 'running'];
            $adjacencyThreshold = max($width, $height) / 15;
            $this->runArtisanCommand('galaxy:generate-gates', [
                'galaxy' => $galaxy->id,
                '--incremental' => true,
                '--regenerate' => true,
                '--adjacency' => $adjacencyThreshold,
            ]);
            $steps[6] = $this->completeStep($steps[6], $stepTimer);

            // Step 8: Generate Trading Hubs (structure only, no inventory)
            $stepTimer = $this->startStepTimer();
            $steps[] = ['step' => 8, 'name' => 'Generating Trading Hub Structure', 'status' => 'running'];
            $hubProbability = config('game_config.inhabited_systems.guaranteed_services.trading_hub', 0.65);
            $this->runArtisanCommand('trading:generate-hubs', [
                'galaxy' => $galaxy->id,
                '--min-gates' => 1,
                '--hub-probability' => $hubProbability,
                '--min-spacing' => 100,
            ]);
            $steps[7] = $this->completeStep($steps[7], $stepTimer);

            // Mark galaxy as processing (async job will complete it)
            $galaxy->status = GalaxyStatus::PROCESSING;
            $galaxy->save();

            // Dispatch background job to complete heavy operations
            CompleteGalaxyCreationJob::dispatch($galaxy->id, [
                'game_mode' => $gameMode,
                'npc_count' => $npcCount,
                'npc_difficulty' => $options['npc_difficulty'] ?? 'medium',
                'skip_mirror' => $options['skip_mirror'] ?? false,
                'skip_pirates' => $options['skip_pirates'] ?? false,
                'skip_precursors' => $options['skip_precursors'] ?? false,
            ]);

            // Gather initial statistics
            $statistics = $this->gatherStatistics($galaxy);
            $executionTime = round(microtime(true) - $startTime, 2);

            return [
                'success' => true,
                'async' => true,
                'galaxy' => [
                    'id' => $galaxy->id,
                    'uuid' => $galaxy->uuid,
                    'name' => $galaxy->name,
                    'width' => $galaxy->width,
                    'height' => $galaxy->height,
                    'game_mode' => $galaxy->game_mode,
                    'status' => 'processing', // Indicates async completion is running
                ],
                'message' => 'Galaxy structure created. Background processing started for inventory, pirates, mirror universe, and NPCs.',
                'statistics' => $statistics,
                'steps' => $steps,
                'pending_steps' => [
                    'Trading hub inventory population',
                    'Cartographer shops',
                    'Market events',
                    'Precursor ship',
                    'Pirate distribution',
                    'Mirror universe',
                    'NPC generation',
                ],
                'execution_time_seconds' => $executionTime,
            ];

        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * Create the galaxy database record
     */
    private function createGalaxyRecord(
        ?string $name,
        int $width,
        int $height,
        string $gameMode,
        ?int $ownerUserId
    ): Galaxy {
        return Galaxy::create([
            'uuid' => Str::uuid(),
            'name' => $name ?? Galaxy::generateUniqueName(),
            'width' => $width,
            'height' => $height,
            'seed' => random_int(1, 999999),
            'distribution_method' => GalaxyDistributionMethod::RANDOM_SCATTER,
            'engine' => GalaxyRandomEngine::MT19937,
            'status' => GalaxyStatus::DRAFT,
            'turn_limit' => 0,
            'is_public' => $gameMode === 'multiplayer',
            'game_mode' => $gameMode,
            'owner_user_id' => $ownerUserId,
        ]);
    }

    /**
     * Seed prerequisites if not already present
     */
    private function seedPrerequisites(Galaxy $galaxy): void
    {
        // Seed Minerals
        if (Mineral::count() === 0) {
            Artisan::call('db:seed', ['--class' => 'MineralSeeder']);
        }

        // Seed Ship Types
        if (Ship::count() === 0) {
            $seeder = new ShipTypesSeeder;
            $seeder->run();
            $seeder->generateShips($galaxy);
        }

        // Seed Upgrade Plans
        if (Plan::count() === 0) {
            Artisan::call('db:seed', ['--class' => 'PlansSeeder']);
        }

        // Seed Pirate Factions
        if (PirateFaction::count() === 0) {
            $seeder = new PirateFactionSeeder;
            $seeder->run();
            $seeder->generatePirateFactions($galaxy);
        }

        // Seed Pirate Captains
        if (PirateCaptain::count() === 0) {
            Artisan::call('db:seed', ['--class' => 'PirateCaptainSeeder']);
        }
    }

    /**
     * Generate initial market events
     */
    private function generateMarketEvents(): void
    {
        $eventCount = random_int(3, 5);

        for ($i = 0; $i < $eventCount; $i++) {
            $this->marketEventGenerator->generateRandomEvent(1.0);
        }
    }

    /**
     * Gather statistics about the created galaxy
     */
    private function gatherStatistics(Galaxy $galaxy): array
    {
        $galaxy->refresh();

        $starCount = $galaxy->pointsOfInterest()
            ->where('type', PointOfInterestType::STAR)
            ->count();

        $inhabitedStars = $galaxy->pointsOfInterest()
            ->stars()
            ->inhabited()
            ->count();

        $uninhabitedStars = $galaxy->pointsOfInterest()
            ->stars()
            ->uninhabited()
            ->count();

        $poiCount = $galaxy->pointsOfInterest()->count();
        $sectorCount = $galaxy->sectors()->count();
        $gateCount = $galaxy->warpGates()->count();

        $pirateCount = WarpLanePirate::whereHas('warpGate', function ($query) use ($galaxy) {
            $query->where('galaxy_id', $galaxy->id);
        })->count();

        $tradingHubCount = TradingHub::whereHas('pointOfInterest', function ($query) use ($galaxy) {
            $query->where('galaxy_id', $galaxy->id);
        })->where('is_active', true)->count();

        $marketEventCount = MarketEvent::where('is_active', true)
            ->where('started_at', '<=', now())
            ->count();

        $npcCount = $galaxy->npcs()->count();

        return [
            'stars' => [
                'total' => $starCount,
                'inhabited' => $inhabitedStars,
                'uninhabited' => $uninhabitedStars,
            ],
            'points_of_interest' => $poiCount,
            'sectors' => $sectorCount,
            'warp_gates' => $gateCount,
            'trading_hubs' => $tradingHubCount,
            'pirate_encounters' => $pirateCount,
            'market_events' => $marketEventCount,
            'npcs' => $npcCount,
        ];
    }

    /**
     * Add NPCs to an existing galaxy
     */
    public function addNpcsToGalaxy(
        Galaxy $galaxy,
        int $count,
        string $difficulty = 'medium',
        ?array $archetypeDistribution = null
    ): array {
        if (! $galaxy->allowsNpcs()) {
            throw new \InvalidArgumentException('This galaxy does not allow NPCs. Change game_mode to single_player or mixed.');
        }

        $npcs = $this->npcGenerationService->generateNpcs(
            $galaxy,
            $count,
            $difficulty,
            $archetypeDistribution
        );

        return [
            'success' => true,
            'npcs_created' => $npcs->count(),
            'npcs' => $npcs->map(fn ($npc) => [
                'uuid' => $npc->uuid,
                'call_sign' => $npc->call_sign,
                'archetype' => $npc->archetype,
                'difficulty' => $npc->difficulty,
                'credits' => $npc->credits,
                'location' => $npc->currentLocation?->name,
            ])->toArray(),
            'statistics' => $this->npcGenerationService->getNpcStatistics($galaxy),
        ];
    }

    // =========================================================================
    // DEBUG HELPER METHODS
    // =========================================================================

    /**
     * Run an Artisan command with optional debug output capture
     */
    private function runArtisanCommand(string $command, array $parameters): int
    {
        if (self::DEBUG_MODE && self::DEBUG_LOG_ARTISAN_OUTPUT) {
            $output = new \Symfony\Component\Console\Output\BufferedOutput;
            $exitCode = Artisan::call($command, $parameters, $output);

            $this->logDebug('artisan', $command, [
                'parameters' => $parameters,
                'exit_code' => $exitCode,
                'output' => $output->fetch(),
            ]);

            return $exitCode;
        }

        return Artisan::call($command, $parameters);
    }

    /**
     * Start timing a step
     *
     * @return array Timer context with start time and query count
     */
    private function startStepTimer(): array
    {
        return [
            'start_time' => microtime(true),
            'start_memory' => memory_get_usage(true),
            'start_queries' => self::DEBUG_MODE ? $this->getQueryCount() : 0,
        ];
    }

    /**
     * Complete a step and add timing information
     */
    private function completeStep(array $step, array $timer, array $extra = []): array
    {
        $step['status'] = 'completed';

        if (self::DEBUG_MODE) {
            $duration = round(microtime(true) - $timer['start_time'], 4);
            $memoryUsed = memory_get_usage(true) - $timer['start_memory'];
            $queriesExecuted = $this->getQueryCount() - $timer['start_queries'];

            $step['debug'] = [
                'duration_seconds' => $duration,
                'memory_delta' => $this->formatBytes($memoryUsed),
                'queries_executed' => $queriesExecuted,
            ];

            $this->logDebug('step_complete', $step['name'], [
                'step' => $step['step'],
                'duration' => $duration,
                'memory_delta' => $memoryUsed,
                'queries' => $queriesExecuted,
            ]);
        }

        return array_merge($step, $extra);
    }

    /**
     * Log a debug entry
     */
    private function logDebug(string $type, string $message, array $context = []): void
    {
        if (! self::DEBUG_MODE) {
            return;
        }

        $this->debugLog[] = [
            'timestamp' => microtime(true),
            'type' => $type,
            'message' => $message,
            'context' => $context,
            'memory' => memory_get_usage(true),
        ];
    }

    /**
     * Get the current query count from the database connection
     */
    private function getQueryCount(): int
    {
        try {
            $queryLog = DB::connection()->getQueryLog();

            return is_array($queryLog) ? count($queryLog) : 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Format bytes to human-readable string
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, 2).' '.$units[$pow];
    }

    /**
     * Get debug summary for the response
     */
    private function getDebugSummary(float $startTime): array
    {
        return [
            'debug_mode' => true,
            'total_duration_seconds' => round(microtime(true) - $startTime, 4),
            'memory' => [
                'current' => $this->formatBytes(memory_get_usage(true)),
                'peak' => $this->formatBytes(memory_get_peak_usage(true)),
            ],
            'total_queries' => $this->getQueryCount() - $this->initialQueryCount,
            'log_entries' => count($this->debugLog),
            'log' => $this->debugLog,
        ];
    }
}
