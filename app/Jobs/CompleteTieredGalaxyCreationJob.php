<?php

namespace App\Jobs;

use App\Enums\Galaxy\GalaxySizeTier;
use App\Enums\Galaxy\GalaxyStatus;
use App\Events\GalaxyCreationCompleted;
use App\Events\GalaxyCreationProgress;
use App\Models\Galaxy;
use App\Services\CoreSystemGenerator;
use App\Services\MarketEventGenerator;
use App\Services\NpcGenerationService;
use App\Services\OuterSystemGenerator;
use App\Services\WarpGate\IncrementalWarpGateGenerator;
use Database\Seeders\PrecursorShipSeeder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

/**
 * Handles the heavy operations of tiered galaxy creation asynchronously.
 *
 * This job continues the galaxy creation process started by TieredGalaxyCreationService::createTieredGalaxyAsync()
 */
class CompleteTieredGalaxyCreationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 3600;  /**
     * Create a job instance to asynchronously finish tiered galaxy creation.
     *
     * @param int $galaxyId The ID of the galaxy to complete.
     * @param GalaxySizeTier $tier The size tier configuration used for generation.
     * @param array $options Runtime options that modify job behavior (e.g., flags to skip precursor or mirror generation, and controls for NPC generation/count).
     */

    public function __construct(
        public int $galaxyId,
        public GalaxySizeTier $tier,
        public array $options = []
    ) {}

    /****
     * Finalizes asynchronous completion of a tiered galaxy creation process for the configured galaxy.
     *
     * Completes remaining generation tasks started by the synchronous phase and updates the galaxy's
     * generation progress and final state. Tasks include deploying fortress defenses, creating trading
     * posts, generating core warp gates and outer frontier stars, creating outer planetary systems,
     * populating mineral deposits, placing dormant gates, optionally seeding precursor ships and a mirror
     * universe, populating trading-hub inventory, generating market events and cartographer shops,
     * and optionally generating NPC players. On successful completion the galaxy is marked ACTIVE and
     * a GalaxyCreationCompleted event is fired. Progress updates are emitted during the run.
     *
     * Recognized runtime options:
     * - skip_precursors (bool): if true, skip seeding precursor ships.
     * - skip_mirror (bool): if true, skip generating a mirror universe.
     * - game_mode (string): 'multiplayer', 'single_player', or 'mixed' (affects NPC generation).
     * - npc_count (int): number of NPCs to generate.
     * - npc_difficulty (string): difficulty level for NPC generation.
     *
     * @throws \Exception If any generation step fails; the galaxy status is set to DRAFT before the exception is rethrown.
     */
    public function handle(): void
    {
        $galaxy = Galaxy::find($this->galaxyId);

        if (! $galaxy) {
            Log::warning("CompleteTieredGalaxyCreationJob: Galaxy {$this->galaxyId} not found");

            return;
        }

        Log::info("CompleteTieredGalaxyCreationJob: Starting async completion for tiered galaxy {$galaxy->name}");

        $startTime = microtime(true);

        try {
            $coreGenerator = app(CoreSystemGenerator::class);
            $outerGenerator = app(OuterSystemGenerator::class);

            // Get core systems (already created in sync phase)
            $coreSystems = $galaxy->corePointsOfInterest()->stars()->get();

            // Step 3: Deploy fortress defenses (20%)
            $this->updateProgress($galaxy, 3, 'Deploying fortress defenses', 20);
            $coreGenerator->deployFortressDefenses($coreSystems);

            // Step 4: Create trading posts (30%)
            $this->updateProgress($galaxy, 4, 'Creating trading posts', 30);
            $coreGenerator->createTradingPosts($coreSystems);

            // Step 5: Generate core warp gate network (40%)
            $this->updateProgress($galaxy, 5, 'Generating core warp gate network', 40);
            $galaxy->warpGates()->delete();
            $warpGateGenerator = new IncrementalWarpGateGenerator(
                adjacencyThreshold: (float) $this->tier->getWarpGateAdjacency(),
                hiddenGatePercentage: 0.02,
                maxGatesPerSystem: 6,
            );
            $warpGateGenerator->generateGatesIncremental($galaxy);

            // Step 6: Generate outer frontier stars (55%)
            $this->updateProgress($galaxy, 7, 'Generating outer frontier stars', 55);
            $outerSystems = $outerGenerator->generateOuterRegion(
                $galaxy,
                $this->tier->getOuterStars(),
                $this->tier->getCoreBoundsArray()
            );

            // Step 8: Create outer planetary systems (65%)
            $this->updateProgress($galaxy, 8, 'Creating outer planetary systems', 65);
            $outerGenerator->generatePlanetarySystems($outerSystems);

            // Step 9: Populate mineral deposits (70%)
            $this->updateProgress($galaxy, 9, 'Populating mineral deposits', 70);
            $allOuterPois = $galaxy->outerPointsOfInterest()->get();
            $outerGenerator->populateMineralDeposits($allOuterPois);

            // Step 10: Place dormant gates (75%)
            $this->updateProgress($galaxy, 10, 'Placing dormant gates in outer region', 75);
            $outerGenerator->generateDormantGates($galaxy, $outerSystems);

            // Step 11: Place precursor ship (80%)
            if (! ($this->options['skip_precursors'] ?? false)) {
                $this->updateProgress($galaxy, 11, 'Placing precursor ship', 80);
                $seeder = app(PrecursorShipSeeder::class);
                $seeder->seedPrecursorShip($galaxy);
            }

            // Step 12: Populate trading hub inventory (85%)
            $this->updateProgress($galaxy, 12, 'Populating trading hub inventory', 85);
            Artisan::call('trading-hub:populate-inventory', [
                'galaxy' => $galaxy->id,
                '--regenerate' => true,
            ]);

            // Step 13: Generate market events
            $this->updateProgress($galaxy, 13, 'Generating market events', 88);
            $marketGenerator = app(MarketEventGenerator::class);
            for ($i = 0; $i < random_int(3, 5); $i++) {
                $marketGenerator->generateRandomEvent(1.0);
            }

            // Step 14: Generate cartographer shops
            $this->updateProgress($galaxy, 14, 'Establishing cartographer shops', 90);
            $spawnRate = config('game_config.star_charts.spawn_rate', 0.3);
            Artisan::call('cartography:generate-shops', [
                'galaxy' => $galaxy->id,
                '--spawn-rate' => $spawnRate,
                '--regenerate' => true,
            ]);

            // Step 15: Generate mirror universe (95%)
            $mirrorGalaxy = null;
            if (! ($this->options['skip_mirror'] ?? false) && config('game_config.mirror_universe.enabled', true)) {
                $this->updateProgress($galaxy, 15, 'Generating mirror universe', 95);
                Artisan::call('galaxy:create-mirror', ['galaxy' => $galaxy->id]);
                $mirrorGalaxy = $galaxy->fresh()->getPairedGalaxy();

                if ($mirrorGalaxy && ! ($this->options['skip_precursors'] ?? false)) {
                    $seeder = app(PrecursorShipSeeder::class);
                    $seeder->seedPrecursorShip($mirrorGalaxy);
                }
            }

            // Step 16: Generate NPCs (if applicable)
            $gameMode = $this->options['game_mode'] ?? 'multiplayer';
            $npcCount = $this->options['npc_count'] ?? 0;
            if ($gameMode === 'single_player' && $npcCount < 1) {
                $npcCount = 5;
            }
            if ($npcCount > 0 && in_array($gameMode, ['single_player', 'mixed'])) {
                $this->updateProgress($galaxy, 16, "Generating {$npcCount} NPC players", 98);
                $npcService = app(NpcGenerationService::class);
                $npcService->generateNpcs(
                    $galaxy,
                    $npcCount,
                    $this->options['npc_difficulty'] ?? 'medium'
                );
            }

            // Step 17: Mark galaxy as active (100%)
            $this->updateProgress($galaxy, 17, 'Activating galaxy', 100, 'completed');
            $galaxy->status = GalaxyStatus::ACTIVE;
            $galaxy->generation_completed_at = now();
            $galaxy->save();

            // Fire completion event
            event(new GalaxyCreationCompleted($galaxy));

            $totalTime = round(microtime(true) - $startTime, 2);
            Log::info("CompleteTieredGalaxyCreationJob: Galaxy {$galaxy->name} completed in {$totalTime}s");

        } catch (\Exception $e) {
            Log::error("CompleteTieredGalaxyCreationJob: Failed for galaxy {$galaxy->name}", [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $galaxy->status = GalaxyStatus::DRAFT;
            $galaxy->save();

            throw $e;
        }
    }

    /**
     * Update the galaxy's creation progress, attempt to broadcast a progress event, and log the step.
     *
     * Updates the Galaxy model's progress state, fires a GalaxyCreationProgress event if possible
     * (broadcast failures are caught and logged at debug level), and records an informational log entry.
     *
     * @param Galaxy $galaxy The galaxy being updated.
     * @param int $step Numeric identifier of the current creation step.
     * @param string $name Human-readable name of the current step.
     * @param int $percentage Completion percentage for the step (0–100).
     * @param string $status Progress status label (e.g., 'running', 'completed'). 
     */
    private function updateProgress(Galaxy $galaxy, int $step, string $name, int $percentage, string $status = 'running'): void
    {
        $galaxy->updateProgress($step, $name, $percentage, $status);

        try {
            event(new GalaxyCreationProgress($galaxy->id, $step, $percentage, $name));
        } catch (\Exception $e) {
            // Broadcasting may fail if not configured
            Log::debug('Progress broadcast failed', ['error' => $e->getMessage()]);
        }

        Log::info("CompleteTieredGalaxyCreationJob: Step {$step} ({$name}) - {$percentage}%");
    }

    /**
     * Handle a job failure by logging the error and reverting the associated galaxy to DRAFT if it exists.
     *
     * @param \Throwable $exception The exception that caused the job to fail.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("CompleteTieredGalaxyCreationJob: Job failed for galaxy {$this->galaxyId}", [
            'exception' => $exception->getMessage(),
        ]);

        $galaxy = Galaxy::find($this->galaxyId);
        if ($galaxy) {
            $galaxy->status = GalaxyStatus::DRAFT;
            $galaxy->save();
        }
    }
}