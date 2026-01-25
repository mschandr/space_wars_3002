<?php

namespace App\Jobs;

use App\Enums\Galaxy\GalaxyStatus;
use App\Models\Galaxy;
use App\Services\MarketEventGenerator;
use App\Services\NpcGenerationService;
use Database\Seeders\PrecursorShipSeeder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

/**
 * Handles the heavy/slow parts of galaxy creation asynchronously:
 * - Trading hub inventory population
 * - Pirate distribution
 * - Mirror universe creation
 * - NPC generation
 * - Market events
 *
 * This allows the API to return quickly with the basic galaxy structure,
 * while these operations complete in the background.
 */
class CompleteGalaxyCreationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 1800; /**
     * Create a job instance to finish creating a galaxy.
     *
     * @param int $galaxyId The ID of the Galaxy to process.
     * @param array $options Options that modify job behavior. Supported keys:
     *                      - 'skip_precursors' (bool): if true, do not spawn precursor ships.
     *                      - 'skip_pirates' (bool): if true, do not distribute pirates.
     *                      - 'skip_mirror' (bool): if true, do not create a mirror universe.
     *                      - 'npc_count' (int): number of NPCs to generate (if > 0).
     *                      - 'game_mode' (string): expected game mode, e.g. 'single_player' or 'mixed'.
     *                      - 'difficulty' (int): NPC generation difficulty level.
     */

    public function __construct(
        public int $galaxyId,
        public array $options = []
    ) {}

    /**
     * Completes asynchronous galaxy creation by running post-creation tasks and updating the galaxy status.
     *
     * Executes a sequence of post-creation steps (inventory population, shop generation, market events,
     * optional precursor ship spawning, pirate distribution, optional mirror universe creation and mirror
     * precursor spawning, and optional NPC generation), records step timings, and marks the galaxy ACTIVE
     * on success. If the target galaxy is not found the job exits early. On failure the galaxy status is
     * reverted to DRAFT and the original exception is rethrown.
     *
     * @throws \Exception Rethrows any exception encountered while executing the post-creation steps.
     */
    public function handle(): void
    {
        $galaxy = Galaxy::find($this->galaxyId);

        if (!$galaxy) {
            Log::warning("CompleteGalaxyCreationJob: Galaxy {$this->galaxyId} not found");
            return;
        }

        // Update status to indicate processing
        $galaxy->status = GalaxyStatus::PROCESSING;
        $galaxy->save();

        Log::info("CompleteGalaxyCreationJob: Starting async completion for galaxy {$galaxy->name}");

        $steps = [];
        $startTime = microtime(true);

        try {
            // Step 1: Populate trading hub inventory (most expensive)
            $steps[] = $this->runStep('Populate Trading Hub Inventory', function () use ($galaxy) {
                Artisan::call('trading-hub:populate-inventory', [
                    'galaxy' => $galaxy->id,
                    '--regenerate' => true,
                ]);
            });

            // Step 2: Generate cartographer shops
            $steps[] = $this->runStep('Generate Cartographer Shops', function () use ($galaxy) {
                $spawnRate = config('game_config.star_charts.spawn_rate', 0.3);
                Artisan::call('cartography:generate-shops', [
                    'galaxy' => $galaxy->id,
                    '--spawn-rate' => $spawnRate,
                    '--regenerate' => true,
                ]);
            });

            // Step 3: Generate market events
            $steps[] = $this->runStep('Generate Market Events', function () {
                $generator = app(MarketEventGenerator::class);
                $eventCount = random_int(3, 5);
                for ($i = 0; $i < $eventCount; $i++) {
                    $generator->generateRandomEvent(1.0);
                }
            });

            // Step 4: Spawn precursor ship (if not skipped)
            if (!($this->options['skip_precursors'] ?? false)) {
                $steps[] = $this->runStep('Spawn Precursor Ship', function () use ($galaxy) {
                    $seeder = app(PrecursorShipSeeder::class);
                    $seeder->seedPrecursorShip($galaxy);
                });
            }

            // Step 5: Distribute pirates (if not skipped)
            if (!($this->options['skip_pirates'] ?? false)) {
                $steps[] = $this->runStep('Distribute Pirates', function () use ($galaxy) {
                    Artisan::call('galaxy:distribute-pirates', [
                        'galaxy' => $galaxy->id,
                    ]);
                });
            }

            // Step 6: Create mirror universe (if not skipped)
            $mirrorGalaxy = null;
            if (!($this->options['skip_mirror'] ?? false) && config('game_config.mirror_universe.enabled', true)) {
                $steps[] = $this->runStep('Create Mirror Universe', function () use ($galaxy, &$mirrorGalaxy) {
                    Artisan::call('galaxy:create-mirror', [
                        'galaxy' => $galaxy->id,
                    ]);
                    $mirrorGalaxy = $galaxy->fresh()->getPairedGalaxy();
                });

                // Spawn precursor in mirror too
                if ($mirrorGalaxy && !($this->options['skip_precursors'] ?? false)) {
                    $steps[] = $this->runStep('Spawn Mirror Precursor', function () use ($mirrorGalaxy) {
                        $seeder = app(PrecursorShipSeeder::class);
                        $seeder->seedPrecursorShip($mirrorGalaxy);
                    });
                }
            }

            // Step 7: Generate NPCs (if applicable)
            $npcCount = $this->options['npc_count'] ?? 0;
            $gameMode = $this->options['game_mode'] ?? 'multiplayer';
            if ($npcCount > 0 && in_array($gameMode, ['single_player', 'mixed'])) {
                $steps[] = $this->runStep("Generate {$npcCount} NPCs", function () use ($galaxy, $npcCount) {
                    $npcDifficulty = $this->options['npc_difficulty'] ?? 'medium';
                    $npcService = app(NpcGenerationService::class);
                    $npcService->generateNpcs($galaxy, $npcCount, $npcDifficulty);
                });
            }

            // Mark galaxy as active
            $galaxy->status = GalaxyStatus::ACTIVE;
            $galaxy->save();

            $totalTime = round(microtime(true) - $startTime, 2);

            Log::info("CompleteGalaxyCreationJob: Galaxy {$galaxy->name} completed in {$totalTime}s", [
                'steps' => $steps,
            ]);

        } catch (\Exception $e) {
            Log::error("CompleteGalaxyCreationJob: Failed for galaxy {$galaxy->name}", [
                'exception' => $e->getMessage(),
                'steps_completed' => $steps,
            ]);

            $galaxy->status = GalaxyStatus::DRAFT;
            $galaxy->save();

            throw $e;
        }
    }

    /**
     * Execute a named step, measure its execution time, and return metadata about the completed step.
     *
     * @param string   $name     The descriptive name of the step.
     * @param callable $callback A callable that performs the step's work.
     * @return array{
     *     step: string,
     *     status: 'completed',
     *     duration: float
     * } An associative array containing the step name, a completion status, and the duration in seconds.
     *
     * @throws \Exception If the callback throws; the exception is propagated.
     */
    private function runStep(string $name, callable $callback): array
    {
        $start = microtime(true);
        Log::info("CompleteGalaxyCreationJob: Starting step '{$name}'");

        try {
            $callback();
            $duration = round(microtime(true) - $start, 2);
            Log::info("CompleteGalaxyCreationJob: Completed step '{$name}' in {$duration}s");

            return [
                'step' => $name,
                'status' => 'completed',
                'duration' => $duration,
            ];
        } catch (\Exception $e) {
            Log::error("CompleteGalaxyCreationJob: Step '{$name}' failed: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Handle a job failure by logging the error and reverting the galaxy to draft.
     *
     * Logs the provided exception message and, if the galaxy exists, sets its status to GalaxyStatus::DRAFT and saves it.
     *
     * @param \Throwable $exception The exception that caused the job to fail.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("CompleteGalaxyCreationJob: Job failed for galaxy {$this->galaxyId}", [
            'exception' => $exception->getMessage(),
        ]);

        // Mark galaxy as failed
        $galaxy = Galaxy::find($this->galaxyId);
        if ($galaxy) {
            $galaxy->status = GalaxyStatus::DRAFT;
            $galaxy->save();
        }
    }
}