<?php

namespace App\Console\Commands;

use App\Models\Galaxy;
use App\Services\MirrorUniverseService;
use Illuminate\Console\Command;

class GalaxyCreateMirror extends Command
{
    protected $signature = 'galaxy:create-mirror
                            {galaxy : The ID or name of the prime galaxy}
                            {--poi= : Specific POI ID for gate location (optional)}
                            {--regenerate : Regenerate mirror if it already exists}
                            {--no-gates : Skip creating warp gate network}
                            {--no-hubs : Skip creating trading hubs}
                            {--no-pirates : Skip distributing pirates}';

    protected $description = 'Create a mirror universe for a galaxy with high-risk, high-reward gameplay';

    public function handle(): int
    {
        $this->info('🌌 Mirror Universe Generator 🌌');
        $this->newLine();

        // Find prime galaxy
        $galaxyIdentifier = $this->argument('galaxy');
        $primeGalaxy = is_numeric($galaxyIdentifier)
            ? Galaxy::find($galaxyIdentifier)
            : Galaxy::where('name', $galaxyIdentifier)->first();

        if (! $primeGalaxy) {
            $this->error("❌ Galaxy not found: {$galaxyIdentifier}");

            return Command::FAILURE;
        }

        if ($primeGalaxy->isMirrorUniverse()) {
            $this->error('❌ Cannot create a mirror of a mirror universe!');

            return Command::FAILURE;
        }

        $this->info("Prime Galaxy: {$primeGalaxy->name} (ID: {$primeGalaxy->id})");
        $this->info("Dimensions: {$primeGalaxy->width}x{$primeGalaxy->height}");
        $this->info("Seed: {$primeGalaxy->seed}");
        $this->newLine();

        $mirrorService = app(MirrorUniverseService::class);

        // Check if mirror already exists
        if ($mirrorService->hasMirrorGalaxy($primeGalaxy) && ! $this->option('regenerate')) {
            $existing = $primeGalaxy->getPairedGalaxy();
            $this->warn("⚠️  Mirror universe already exists: {$existing->name} (ID: {$existing->id})");
            $this->warn('Use --regenerate to recreate it');

            return Command::SUCCESS;
        }

        // Step 1: Create mirror galaxy
        $this->info('Step 1: Creating mirror galaxy...');
        $mirrorGalaxy = $mirrorService->createMirrorGalaxy($primeGalaxy);
        $this->info("✅ Created: {$mirrorGalaxy->name} (ID: {$mirrorGalaxy->id})");
        $this->newLine();

        // Step 2: Generate POIs (identical structure due to same seed)
        $this->info('Step 2: Generating points of interest...');
        $starCount = $primeGalaxy->pointsOfInterest()
            ->where('type', \App\Enums\PointsOfInterest\PointOfInterestType::STAR)
            ->count();

        $this->call('galaxy:expand', [
            'galaxy' => $mirrorGalaxy->id,
            '--stars' => $starCount,
        ]);
        $this->newLine();

        // Step 3: Generate sectors
        $this->info('Step 3: Generating sectors...');
        $this->call('galaxy:generate-sectors', [
            'galaxy' => $mirrorGalaxy->id,
        ]);
        $this->newLine();

        // Step 4: Generate warp gates
        if (! $this->option('no-gates')) {
            $this->info('Step 4: Generating warp gate network...');
            $this->call('galaxy:generate-gates', [
                'galaxy' => $mirrorGalaxy->id,
            ]);
            $this->newLine();
        }

        // Step 5: Generate trading hubs
        if (! $this->option('no-hubs')) {
            $this->info('Step 5: Generating trading hubs...');
            $this->call('trading:generate-hubs', [
                'galaxy' => $mirrorGalaxy->id,
            ]);
            $this->newLine();
        }

        // Step 6: Distribute pirates
        if (! $this->option('no-pirates')) {
            $this->info('Step 6: Distributing pirates (with boosted difficulty)...');
            $this->call('galaxy:distribute-pirates', [
                'galaxy' => $mirrorGalaxy->id,
            ]);
            $this->newLine();
        }

        // Step 7: Create mirror gate pair
        $this->info('Step 7: Creating mirror universe portal...');

        // Select POI for gate
        $poiId = $this->option('poi');
        if ($poiId) {
            $primePoi = $primeGalaxy->pointsOfInterest()->find($poiId);
            if (! $primePoi) {
                $this->error("❌ POI not found: {$poiId}");

                return Command::FAILURE;
            }
        } else {
            $primePoi = $mirrorService->selectRandomGateLocation($primeGalaxy);
            if (! $primePoi) {
                $this->error('❌ No suitable POI found for gate placement');

                return Command::FAILURE;
            }
        }

        $gates = $mirrorService->createMirrorGatePair($primeGalaxy, $mirrorGalaxy, $primePoi);

        $this->info("✅ Mirror portal created at: {$primePoi->name} ({$primePoi->x}, {$primePoi->y})");
        $this->info("   Entry Gate ID: {$gates['entry_gate']->id} (HIDDEN - requires sensors level ".config('game_config.mirror_universe.required_sensor_level', 5).')');
        $this->info("   Return Gate ID: {$gates['return_gate']->id} (VISIBLE)");
        $this->newLine();

        // Display summary
        $this->info('═══════════════════════════════════════');
        $this->info('✨ MIRROR UNIVERSE CREATED ✨');
        $this->info('═══════════════════════════════════════');
        $this->info("Prime Galaxy: {$primeGalaxy->name}");
        $this->info("Mirror Galaxy: {$mirrorGalaxy->name}");
        $this->info("Portal Location: {$primePoi->name}");
        $this->newLine();
        $this->info('Modifiers:');
        $modifiers = $mirrorGalaxy->getMirrorModifiers();
        $this->info("  • Resources: {$modifiers['resource_multiplier']}x");
        $this->info("  • Rare Minerals: {$modifiers['rare_mineral_spawn_rate']}x");
        $this->info("  • Trading Prices: {$modifiers['price_boost']}x");
        $this->info("  • Pirate Difficulty: {$modifiers['pirate_difficulty_boost']}x");
        $this->newLine();
        $this->info("Return Cooldown: ".config('game_config.mirror_universe.return_cooldown_hours', 24).' hours');
        $this->newLine();
        $this->info('⚠️  The mirror universe awaits brave explorers... ⚠️');

        return Command::SUCCESS;
    }
}
