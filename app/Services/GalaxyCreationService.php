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

    /**
     * Construct the GalaxyCreationService with its required generators.
     *
     * @param NpcGenerationService $npcGenerationService Service used to generate NPCs and their metadata.
     * @param MarketEventGenerator $marketEventGenerator Service used to create market events during galaxy creation.
     */
    public function __construct(
        NpcGenerationService $npcGenerationService,
        MarketEventGenerator $marketEventGenerator
    ) {
        $this->npcGenerationService = $npcGenerationService;
        $this->marketEventGenerator = $marketEventGenerator;
    }

    /**
         * Orchestrates creation of a complete, playable galaxy from provided options.
         *
         * Accepts an options array to configure generation parameters, performs the full
         * creation workflow (record creation, seeding, star/grid/gate/hub generation,
         * optional precursor/pirate/mirror/NPC steps), updates the galaxy to active,
         * and returns creation results and statistics. When DEBUG_MODE is enabled the
         * result includes a debug summary.
         *
         * Supported option keys:
         * - name: (string|null) Galaxy display name; if omitted a unique name is generated.
         * - width: (int) Galaxy width in units (default 300).
         * - height: (int) Galaxy height in units (default 300).
         * - stars: (int) Number of stars/POIs to generate (default 1000).
         * - grid_size: (int) Sector grid size used when generating sectors (default 10).
         * - game_mode: (string) Game mode, e.g. 'multiplayer', 'single_player', 'mixed'.
         * - owner_user_id: (int|null) ID of the owning user, if any.
         * - npc_count: (int) Number of NPC players to create (may be adjusted for single_player).
         * - npc_difficulty: (string) NPC difficulty level (default 'medium').
         * - skip_mirror: (bool) If true, skip creating a mirror universe.
         * - skip_pirates: (bool) If true, skip pirate distribution.
         * - skip_precursors: (bool) If true, skip spawning precursor ships.
         *
         * @param array $options Configuration and feature flags for galaxy creation.
         * @return array Result object containing:
         *               - success: (bool) true on successful creation.
         *               - galaxy: (array) Basic galaxy metadata (id, uuid, name, width, height, game_mode, status).
         *               - mirror_galaxy: (array|null) Mirror galaxy metadata when created.
         *               - npcs: (array) Created NPC summaries.
         *               - statistics: (array) Counts and summary of stars, sectors, hubs, gates, events, NPCs, etc.
         *               - steps: (array) Per-step execution records and timings.
         *               - execution_time_seconds: (float) Total creation duration.
         *               - debug: (array) Debug summary present only when DEBUG_MODE is enabled.
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
     * Start creation of a galaxy's core structure and enqueue background jobs to finish heavy operations.
     *
     * The method performs fast, synchronous steps to create the galaxy record, seed prerequisites,
     * generate stars/POIs, sectors, inhabited designations, warp gates, and trading hub structure,
     * then marks the galaxy as processing and dispatches a background job to complete inventory
     * population, market events, precursor ships, pirate distribution, mirror universe creation, and NPC generation.
     *
     * @param array $options Configuration for galaxy creation. Recognized keys:
     *                       - name: (string|null) galaxy name
     *                       - width: (int) galaxy width
     *                       - height: (int) galaxy height
     *                       - stars: (int) number of stars to generate
     *                       - grid_size: (int) sector grid size
     *                       - game_mode: (string) e.g., 'multiplayer' or 'single_player'
     *                       - owner_user_id: (int|null) owner user id
     *                       - npc_count: (int) number of NPCs to generate (adjusted for single player)
     *                       - npc_difficulty: (string) NPC difficulty level
     *                       - skip_mirror: (bool) skip mirror universe creation
     *                       - skip_pirates: (bool) skip pirate distribution
     *                       - skip_precursors: (bool) skip precursor ship seeding
     * @return array Summary of the initiated asynchronous creation:
     *               - success: `true` if initial steps completed
     *               - async: `true` indicating background processing is running
     *               - galaxy: brief galaxy metadata (id, uuid, name, width, height, game_mode, status)
     *               - message: human-readable status message
     *               - statistics: initial counts for stars, POIs, sectors, hubs, etc.
     *               - steps: array of completed step entries with timing/debug info
     *               - pending_steps: list of actions reserved for background processing
     *               - execution_time_seconds: time taken to run the synchronous portion
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
     * Create a new Galaxy record with default seed, distribution, engine, and initial DRAFT status.
     *
     * @param string|null $name Optional custom name; a unique name will be generated if null.
     * @param int $width Galaxy width in grid units.
     * @param int $height Galaxy height in grid units.
     * @param string $gameMode Game mode identifier (e.g., "single_player" or "multiplayer"); determines visibility and mode settings.
     * @param int|null $ownerUserId Optional user ID to assign as the galaxy owner.
     * @return \App\Models\Galaxy The persisted Galaxy model initialized in DRAFT state.
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
     * Ensure core game reference data exists and seed any missing datasets.
     *
     * Seeds minerals, ship types (and generates ships for the provided galaxy),
     * upgrade plans, pirate factions (and generates factions for the provided galaxy),
     * and pirate captains when their respective tables are empty.
     *
     * @param Galaxy $galaxy The galaxy context used when generating galaxy-specific records (ships and pirate factions).
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
     * Create a small set of initial market events for the galaxy.
     *
     * Generates between three and five market events using the configured MarketEventGenerator.
     */
    private function generateMarketEvents(): void
    {
        $eventCount = random_int(3, 5);

        for ($i = 0; $i < $eventCount; $i++) {
            $this->marketEventGenerator->generateRandomEvent(1.0);
        }
    }

    /**
         * Compute counts of key entities and return a structured statistics summary for the given galaxy.
         *
         * @param Galaxy $galaxy The galaxy to analyze.
         * @return array{
         *     stars: array{total:int, inhabited:int, uninhabited:int},
         *     points_of_interest:int,
         *     sectors:int,
         *     warp_gates:int,
         *     trading_hubs:int,
         *     pirate_encounters:int,
         *     market_events:int,
         *     npcs:int
         * }
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
         * Generate and add NPCs to the given galaxy.
         *
         * @param Galaxy $galaxy The target galaxy where NPCs will be created.
         * @param int $count The number of NPCs to generate.
         * @param string $difficulty NPC difficulty level (e.g., 'easy', 'medium', 'hard').
         * @param array|null $archetypeDistribution Optional map of archetype => weight to bias archetype selection.
         * @return array{
         *     success: bool,
         *     npcs_created: int,
         *     npcs: array<int,array{uuid:string,call_sign:string,archetype:string,difficulty:string,credits:int,location:?string}>,
         *     statistics: array
         * }
         *
         * @throws \InvalidArgumentException If the galaxy does not allow NPCs.
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
         * Execute an Artisan console command and return its exit code.
         *
         * When debug mode and Artisan output logging are enabled, captures the command's output
         * and records it in the service debug log along with parameters and the exit code.
         *
         * @param string $command The Artisan command name (e.g. 'galaxy:expand').
         * @param array $parameters Associative array of command arguments and options.
         * @return int The command's exit code. 
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
     * Create a timer context capturing the current time, memory usage, and starting DB query count.
     *
     * @return array{
     *     start_time: float,   // current timestamp in seconds with microsecond precision
     *     start_memory: int,   // current memory usage in bytes
     *     start_queries: int   // DB query count at start (0 if debug mode is disabled)
     * }
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
         * Mark a step as completed and, when debugging is enabled, attach timing, memory, and query metrics.
         *
         * @param array $step The step record to complete; will have its `status` set to `"completed"` and may receive a `debug` sub-array.
         * @param array $timer Timer context with keys `start_time` (float), `start_memory` (int), and `start_queries` (int) used to compute metrics.
         * @param array $extra Optional additional data to merge into the completed step.
         * @return array The completed step array, merged with any `$extra` data and including debug metrics when debugging is enabled.
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
         * Append a debug entry to the internal debug log when debugging is enabled.
         *
         * The entry records the timestamp, entry type, message, provided context, and current memory usage.
         *
         * @param string $type Short label categorizing the debug entry (e.g., 'step_start', 'step_complete', 'error').
         * @param string $message Human-readable message describing the event.
         * @param array $context Additional contextual data to include with the entry.
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
         * Retrieve the number of database queries recorded on the current connection.
         *
         * @return int The number of recorded queries, or 0 if the query log is unavailable.
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
     * Convert a byte count into a human-readable size string.
     *
     * @param int $bytes The number of bytes to format.
     * @return string Human-readable size with unit, rounded to two decimal places (for example, `1.23 MB`).
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
         * Produce a debug summary containing timing, memory, query and log information since the given start time.
         *
         * @param float $startTime Unix timestamp (in seconds, with microseconds) marking the beginning of the measured interval.
         * @return array{
         *     debug_mode: bool,
         *     total_duration_seconds: float,
         *     memory: array{current: string, peak: string},
         *     total_queries: int,
         *     log_entries: int,
         *     log: array
         * } Summary data including duration, formatted memory usage, query delta, and collected debug log entries.
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