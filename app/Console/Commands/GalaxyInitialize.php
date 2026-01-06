<?php

namespace App\Console\Commands;

use App\Enums\Galaxy\GalaxyDistributionMethod;
use App\Enums\Galaxy\GalaxyRandomEngine;
use App\Enums\Galaxy\GalaxyStatus;
use App\Models\Galaxy;
use App\Services\MarketEventGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

class GalaxyInitialize extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'galaxy:initialize
                            {name? : The name of the galaxy (auto-generated if not provided)}
                            {--width=1000 : Width of the galaxy}
                            {--height=1000 : Height of the galaxy}
                            {--stars=1000 : Number of stars to generate}
                            {--density=scatter : Distribution method (scatter, poisson, cluster)}
                            {--grid-size=10 : Sector grid size (default 10x10)}
                            {--skip-gates : Skip warp gate generation}
                            {--skip-pirates : Skip pirate distribution}
                            {--skip-inventory : Skip trading hub inventory population}
                            {--skip-mirror : Skip mirror universe creation}
                            {--mirror-poi= : Specific POI ID for mirror gate placement}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Initialize a complete galaxy with all points of interest, gates, and trading hubs in one step';

    private Galaxy $galaxy;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('════════════════════════════════════════════════════════════════');
        $this->info('  GALAXY INITIALIZATION - COMPLETE UNIVERSE SETUP');
        $this->info('════════════════════════════════════════════════════════════════');
        $this->newLine();

        $name = $this->argument('name') ?: $this->generateGalaxyName();
        $width = (int) $this->option('width');
        $height = (int) $this->option('height');
        $starCount = (int) $this->option('stars');
        $density = $this->option('density');
        $gridSize = (int) $this->option('grid-size');

        // Display configuration
        $this->info("Configuration:");
        $this->line("  Name: {$name}" . ($this->argument('name') ? '' : ' (auto-generated)'));
        $this->line("  Dimensions: {$width}x{$height}");
        $this->line("  Stars: {$starCount}");
        $this->line("  Density: {$density}");
        $this->line("  Sector Grid: {$gridSize}x{$gridSize}");
        $this->newLine();

        // Step 0: Verify/Seed Prerequisites
        $this->step(0, 'Verifying Database Prerequisites');
        $this->seedPrerequisites();

        // Step 1: Create Galaxy
        $this->step(1, 'Creating Galaxy');
        $this->galaxy = $this->createGalaxy($name, $width, $height, $density);
        $this->success("Galaxy created: {$this->galaxy->name} (ID: {$this->galaxy->id})");

        // Step 2: Generate Stars and POIs
        $this->step(2, "Generating {$starCount} Stars and Points of Interest");
        $this->callCommand('galaxy:expand', [
            'galaxy' => $this->galaxy->id,
            '--stars' => $starCount,
        ]);

        // Step 3: Assign Mineral Production
        $this->step(3, 'Assigning Mineral Production to POIs');
        $this->callCommand('trading:assign-production', [
            'galaxy' => $this->galaxy->id,
        ]);

        // Step 4: Generate Sectors
        $this->step(4, "Generating Sector Grid ({$gridSize}x{$gridSize})");
        $this->callCommand('galaxy:generate-sectors', [
            'galaxy' => $this->galaxy->id,
            '--grid-size' => $gridSize,
        ]);

        // Step 5: Generate Warp Gates
        if (!$this->option('skip-gates')) {
            $this->step(5, 'Generating Warp Gates');

            // Use incremental generator for large galaxies (>500 stars)
            if ($starCount > 500) {
                $this->info('  Using incremental gate generator for large galaxy...');
                $this->callCommand('galaxy:generate-gates-incremental', [
                    'galaxy' => $this->galaxy->id,
                ]);
            } else {
                $this->callCommand('galaxy:generate-gates', [
                    'galaxy' => $this->galaxy->id,
                ]);
            }
        } else {
            $this->warn('⊘ Skipping warp gate generation');
        }

        // Step 6: Generate Trading Hubs (Sparse distribution - 80% empty for colonization)
        if (!$this->option('skip-gates')) {
            $this->step(6, 'Generating Trading Hubs at Warp Gate Intersections');
            $this->info('  Using sparse distribution to keep universe mostly empty for colonization...');
            $this->callCommand('trading:generate-hubs', [
                'galaxy' => $this->galaxy->id,
                '--min-gates' => 3,
                '--hub-probability' => 0.25,
                '--min-spacing' => 100,
            ]);
        } else {
            $this->warn('⊘ Skipping trading hub generation (requires warp gates)');
        }

        // Step 7: Populate Trading Hub Inventory
        if (!$this->option('skip-inventory')) {
            $this->step(7, 'Populating Trading Hub Inventory');
            $this->callCommand('trading-hub:populate-inventory', [
                '--regenerate' => true,
            ]);
        } else {
            $this->warn('⊘ Skipping trading hub inventory population');
        }

        // Step 8: Generate Initial Market Events
        $this->step(8, 'Generating Initial Market Events');
        $this->generateInitialMarketEvents();

        // Step 9: Distribute Pirates
        if (!$this->option('skip-pirates')) {
            $this->step(9, 'Distributing Pirates to Warp Lanes');
            $this->callCommand('galaxy:distribute-pirates', [
                'galaxy' => $this->galaxy->id,
            ]);
        } else {
            $this->warn('⊘ Skipping pirate distribution');
        }

        // Step 10: Create Mirror Universe
        if (!$this->option('skip-mirror') && config('game_config.mirror_universe.enabled', true)) {
            $this->step(10, '🌌 Creating Mirror Universe (High-Risk, High-Reward) 🌌');
            $this->callCommand('galaxy:create-mirror', array_filter([
                'galaxy' => $this->galaxy->id,
                '--poi' => $this->option('mirror-poi'),
            ]));
        } else {
            if ($this->option('skip-mirror')) {
                $this->warn('⊘ Skipping mirror universe creation');
            } else {
                $this->warn('⊘ Mirror universe disabled in config');
            }
        }

        // Final Summary
        $this->newLine();
        $this->info('════════════════════════════════════════════════════════════════');
        $this->info('  GALAXY INITIALIZATION COMPLETE');
        $this->info('════════════════════════════════════════════════════════════════');
        $this->newLine();

        $this->displaySummary();

        return Command::SUCCESS;
    }

    private function createGalaxy(string $name, int $width, int $height, string $density): Galaxy
    {
        return Galaxy::create([
            'uuid' => Str::uuid(),
            'name' => $name,
            'width' => $width,
            'height' => $height,
            'seed' => random_int(1, 999999),
            'distribution_method' => GalaxyDistributionMethod::fromName($density),
            'engine' => GalaxyRandomEngine::MT19937,
            'status' => GalaxyStatus::ACTIVE,
            'turn_limit' => 0,
            'is_public' => false,
        ]);
    }

    private function step(int $number, string $description): void
    {
        $this->newLine();
        $this->info("═══════════════════════════════════════════════════════════════");
        $this->info("  STEP {$number}: {$description}");
        $this->info("═══════════════════════════════════════════════════════════════");
        $this->newLine();
    }

    private function success(string $message): void
    {
        $this->newLine();
        $this->info("✅ {$message}");
        $this->newLine();
    }

    private function callCommand(string $command, array $parameters = []): void
    {
        // Call the command and show its output
        Artisan::call($command, $parameters, $this->output);
    }

    private function displaySummary(): void
    {
        // Reload galaxy with relationships
        $this->galaxy->refresh();

        $starCount = $this->galaxy->pointsOfInterest()
            ->where('type', \App\Enums\PointsOfInterest\PointOfInterestType::STAR)
            ->count();

        $poiCount = $this->galaxy->pointsOfInterest()->count();
        $sectorCount = $this->galaxy->sectors()->count();
        $gateCount = $this->galaxy->warpGates()->count();

        $pirateCount = \App\Models\WarpLanePirate::whereHas('warpGate', function ($query) {
            $query->where('galaxy_id', $this->galaxy->id);
        })->count();

        $tradingHubCount = \App\Models\TradingHub::whereHas('pointOfInterest', function ($query) {
            $query->where('galaxy_id', $this->galaxy->id);
        })->where('is_active', true)->count();

        $shipInventoryCount = \App\Models\TradingHubShip::whereHas('tradingHub.pointOfInterest', function ($query) {
            $query->where('galaxy_id', $this->galaxy->id);
        })->sum('quantity');

        $marketEventCount = \App\Models\MarketEvent::where('is_active', true)
            ->where('started_at', '<=', now())
            ->count();

        // Count POIs with mineral production
        $productionCount = $this->galaxy->pointsOfInterest()
            ->whereNotNull('attributes->produces')
            ->count();

        // Check for mirror universe
        $mirrorGalaxy = $this->galaxy->getPairedGalaxy();
        $hasMirror = $mirrorGalaxy !== null;

        $rows = [
            ['Galaxy ID', $this->galaxy->id],
            ['Galaxy Name', $this->galaxy->name],
            ['Dimensions', "{$this->galaxy->width}x{$this->galaxy->height}"],
            ['Stars', number_format($starCount)],
            ['Total POIs', number_format($poiCount)],
            ['Mineral Sources', number_format($productionCount)],
            ['Sectors', number_format($sectorCount)],
            ['Warp Gates', number_format($gateCount)],
            ['Trading Hubs', number_format($tradingHubCount)],
            ['Ships in Stock', number_format($shipInventoryCount)],
            ['Pirate Encounters', number_format($pirateCount)],
            ['Active Market Events', number_format($marketEventCount)],
        ];

        if ($hasMirror) {
            $rows[] = ['─────────────', '─────────'];
            $rows[] = ['🌌 Mirror Universe', '✅ Created'];
            $rows[] = ['Mirror Galaxy ID', $mirrorGalaxy->id];
            $rows[] = ['Mirror Galaxy Name', $mirrorGalaxy->name];
        }

        $this->table(['Component', 'Count'], $rows);

        $this->newLine();
        $this->info("🌌 Galaxy '{$this->galaxy->name}' is ready for exploration!");
        $this->info("   View it with: php artisan galaxy:view {$this->galaxy->id}");
        $this->newLine();
    }

    private function generateInitialMarketEvents(): void
    {
        $generator = app(MarketEventGenerator::class);

        // Generate 3-5 initial market events for the new galaxy
        $eventCount = rand(3, 5);
        $this->info("Generating {$eventCount} initial market events...");

        $progressBar = $this->output->createProgressBar($eventCount);
        $progressBar->start();

        $generated = 0;
        for ($i = 0; $i < $eventCount; $i++) {
            $event = $generator->generateRandomEvent(1.0); // 100% probability
            if ($event) {
                $generated++;
            }
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();
        $this->newLine();

        if ($generated > 0) {
            $this->info("✅ Created {$generated} market event(s)");

            // Show the generated events
            $events = \App\Models\MarketEvent::where('is_active', true)
                ->orderBy('created_at', 'desc')
                ->take($generated)
                ->get();

            foreach ($events as $event) {
                $mineralName = $event->mineral ? $event->mineral->name : 'All Minerals';
                $this->line("  • {$event->event_type->getDisplayName()}: {$mineralName} ({$event->price_multiplier}x for {$event->getDurationString()})");
            }
        }
    }

    private function seedPrerequisites(): void
    {
        $seeded = [];
        $skipped = [];

        // Check and seed Minerals
        if (\App\Models\Mineral::count() === 0) {
            $this->info('  Seeding minerals...');
            Artisan::call('db:seed', ['--class' => 'MineralSeeder'], $this->output);
            $seeded[] = 'Minerals';
        } else {
            $skipped[] = 'Minerals (already exist)';
        }

        // Check and seed Ship Types
        if (\App\Models\Ship::count() === 0) {
            $this->info('  Seeding ship types...');
            Artisan::call('db:seed', ['--class' => 'ShipTypesSeeder'], $this->output);
            $seeded[] = 'Ship Types';
        } else {
            $skipped[] = 'Ship Types (already exist)';
        }

        // Check and seed Upgrade Plans
        if (\App\Models\Plan::count() === 0) {
            $this->info('  Seeding upgrade plans...');
            Artisan::call('db:seed', ['--class' => 'PlansSeeder'], $this->output);
            $seeded[] = 'Upgrade Plans';
        } else {
            $skipped[] = 'Upgrade Plans (already exist)';
        }

        // Check and seed Pirate Factions
        if (\App\Models\PirateFaction::count() === 0) {
            $this->info('  Seeding pirate factions...');
            Artisan::call('db:seed', ['--class' => 'PirateFactionSeeder'], $this->output);
            $seeded[] = 'Pirate Factions';
        } else {
            $skipped[] = 'Pirate Factions (already exist)';
        }

        // Check and seed Pirate Captains
        if (\App\Models\PirateCaptain::count() === 0) {
            $this->info('  Seeding pirate captains...');
            Artisan::call('db:seed', ['--class' => 'PirateCaptainSeeder'], $this->output);
            $seeded[] = 'Pirate Captains';
        } else {
            $skipped[] = 'Pirate Captains (already exist)';
        }

        // Check and seed Precursor Ships
        if (\App\Models\PrecursorShip::count() === 0) {
            $this->info('  Seeding precursor ships...');
            Artisan::call('db:seed', ['--class' => 'PrecursorShipSeeder'], $this->output);
            $seeded[] = 'Precursor Ships';
        } else {
            $skipped[] = 'Precursor Ships (already exist)';
        }

        $this->newLine();
        if (!empty($seeded)) {
            $this->info('✅ Seeded: ' . implode(', ', $seeded));
        }
        if (!empty($skipped)) {
            $this->line('⊘ Skipped: ' . implode(', ', $skipped));
        }
        $this->newLine();
    }

    private function generateGalaxyName(): string
    {
        $prefixes = [
            'Andromeda', 'Centaurus', 'Cygnus', 'Draco', 'Fornax',
            'Hydra', 'Lyra', 'Orion', 'Perseus', 'Phoenix',
            'Scorpius', 'Taurus', 'Ursa', 'Vela', 'Virgo',
            'Nova', 'Nebula', 'Stellar', 'Cosmic', 'Celestial',
            'Galactic', 'Astral', 'Ethereal', 'Quantum', 'Void'
        ];

        $descriptors = [
            'Expanse', 'Rift', 'Cluster', 'Nexus', 'Reach',
            'Frontier', 'Sector', 'Domain', 'Territory', 'Quadrant',
            'Region', 'Zone', 'Array', 'Collective', 'Network',
            'Void', 'Shroud', 'Abyss', 'Horizon', 'Infinity'
        ];

        $suffixes = [
            'Prime', 'Alpha', 'Beta', 'Gamma', 'Delta',
            'Major', 'Minor', 'Central', 'Outer', 'Inner',
            'Rising', 'Ascending', 'Eternal', 'Ancient', 'New',
            'Lost', 'Hidden', 'Unknown', 'Distant', 'Remote'
        ];

        // Generate different name patterns
        $pattern = rand(1, 4);

        return match($pattern) {
            1 => $prefixes[array_rand($prefixes)] . ' ' . $descriptors[array_rand($descriptors)],
            2 => $prefixes[array_rand($prefixes)] . ' ' . $suffixes[array_rand($suffixes)],
            3 => $prefixes[array_rand($prefixes)] . ' ' . $descriptors[array_rand($descriptors)] . ' ' . $suffixes[array_rand($suffixes)],
            4 => 'The ' . $prefixes[array_rand($prefixes)] . ' ' . $descriptors[array_rand($descriptors)],
        };
    }
}
