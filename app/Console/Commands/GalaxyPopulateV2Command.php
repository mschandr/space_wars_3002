<?php

namespace App\Console\Commands;

use App\Models\Galaxy;
use App\Services\GalaxyGenerationV2Service;
use Illuminate\Console\Command;

class GalaxyPopulateV2Command extends Command
{
    protected $signature = 'galaxy:populate-v2
                            {galaxy : Galaxy ID or UUID}
                            {--step=all : Generation phase (stars|gates|player|supernode|systems|all)}
                            {--user= : User ID for player creation (required if step=player or step=all)}
                            {--force : Skip confirmation}';

    protected $description = 'Populate galaxy using V2 generation (discrete, resumable phases)';

    public function handle(GalaxyGenerationV2Service $service): int
    {
        $this->newLine();
        $this->info('═══════════════════════════════════════════════════════════════');
        $this->info('  Galaxy Population V2 - Discrete Phase Generation');
        $this->info('═══════════════════════════════════════════════════════════════');
        $this->newLine();

        // Resolve galaxy
        $galaxyId = $this->argument('galaxy');
        $galaxy = Galaxy::findOrFail($galaxyId);

        $step = $this->option('step');
        $force = $this->option('force');

        // Validate step
        $validSteps = ['stars', 'gates', 'player', 'supernode', 'systems', 'all'];
        if (!in_array($step, $validSteps)) {
            $this->error("Invalid step: {$step}");
            $this->line("Valid steps: " . implode(', ', $validSteps));
            return self::FAILURE;
        }

        // Show summary
        $this->line("Galaxy: <fg=cyan>{$galaxy->name}</> ({$galaxy->id})");
        $this->line("Status: <fg=cyan>{$galaxy->status->value}</>");
        $this->line("Step: <fg=cyan>{$step}</>");
        $this->newLine();

        if (!$force && !$this->confirm('Proceed with this step?')) {
            $this->info('Cancelled.');
            return self::FAILURE;
        }

        $this->newLine();

        try {
            // Execute requested steps
            if ($step === 'all') {
                return $this->runAllSteps($service, $galaxy);
            }

            return $this->runStep($service, $galaxy, $step);
        } catch (\Exception $e) {
            $this->error("Error: {$e->getMessage()}");
            if ($this->option('verbose')) {
                $this->error($e->getTraceAsString());
            }
            return self::FAILURE;
        }
    }

    /**
     * Run all steps sequentially.
     */
    private function runAllSteps(GalaxyGenerationV2Service $service, Galaxy $galaxy): int
    {
        $steps = ['stars', 'gates', 'systems'];
        $userId = $this->option('user');

        if (!$userId) {
            $this->warn('No user specified. Skipping player creation steps.');
            $playerSteps = [];
        } else {
            $playerSteps = ['player', 'supernode'];
        }

        foreach (array_merge($steps, $playerSteps) as $step) {
            $this->newLine();
            $this->info("─ Running step: {$step}");
            if ($this->runStep($service, $galaxy, $step, $userId) === self::FAILURE) {
                return self::FAILURE;
            }
        }

        $this->newLine();
        $this->info('═══════════════════════════════════════════════════════════════');
        $this->info('✓ Galaxy population complete!');
        $this->info('═══════════════════════════════════════════════════════════════');
        $this->newLine();

        return self::SUCCESS;
    }

    /**
     * Run a single step.
     */
    private function runStep(GalaxyGenerationV2Service $service, Galaxy $galaxy, string $step, ?string $userId = null): int
    {
        $startTime = microtime(true);

        try {
            $result = match ($step) {
                'stars' => $this->stepCreateStars($service, $galaxy),
                'gates' => $this->stepCreateWarpLanes($service, $galaxy),
                'player' => $this->stepCreatePlayer($service, $galaxy, $userId),
                'supernode' => $this->stepCreateSupernodeForPlayer($service, $galaxy),
                'systems' => $this->stepPopulateConnectedSystems($service, $galaxy),
                default => 0,
            };

            $elapsed = round((microtime(true) - $startTime) * 1000);
            $this->line("  ✓ Completed in {$elapsed}ms");

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("  ✗ Failed: {$e->getMessage()}");
            throw $e;
        }
    }

    private function stepCreateStars(GalaxyGenerationV2Service $service, Galaxy $galaxy): int
    {
        $this->info('Creating stars (core + outer regions)...');
        return $service->createStars($galaxy);
    }

    private function stepCreateWarpLanes(GalaxyGenerationV2Service $service, Galaxy $galaxy): int
    {
        $this->info('Creating warp lanes (distance-based distribution)...');
        return $service->createWarpLanes($galaxy);
    }

    private function stepCreatePlayer(GalaxyGenerationV2Service $service, Galaxy $galaxy, ?string $userId): int
    {
        if (!$userId) {
            throw new \Exception('User ID required for player creation');
        }

        $user = \App\Models\User::findOrFail($userId);
        $this->info("Creating player {$user->email}...");
        $player = $service->createPlayer($galaxy, $user);

        return 1;
    }

    private function stepCreateSupernodeForPlayer(GalaxyGenerationV2Service $service, Galaxy $galaxy): int
    {
        $player = $galaxy->players()->first();
        if (!$player) {
            throw new \Exception('No player found in this galaxy. Run --step=player first.');
        }

        $this->info("Creating supernode for player {$player->user->email}...");
        $supernode = $service->createSupernodeForPlayer($player);
        $this->line("  → Supernode: {$supernode->name}");

        return 1;
    }

    private function stepPopulateConnectedSystems(GalaxyGenerationV2Service $service, Galaxy $galaxy): int
    {
        $this->info('Populating connected systems (planets, minerals, defenses)...');
        return $service->populateConnectedSystems($galaxy);
    }
}
