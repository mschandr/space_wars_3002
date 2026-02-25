<?php

namespace App\Console\Commands;

use App\Enums\Galaxy\GalaxyDistributionMethod;
use App\Enums\Galaxy\GalaxyRandomEngine;
use App\Enums\Galaxy\GalaxyStatus;
use App\Enums\PointsOfInterest\PointOfInterestType;
use App\Models\Galaxy;
use App\Models\MarketEvent;
use App\Models\Mineral;
use App\Models\PirateCaptain;
use App\Models\PirateFaction;
use App\Models\Plan;
use App\Models\Ship;
use App\Models\TradingHub;
use App\Models\TradingHubShip;
use App\Models\WarpLanePirate;
use App\Services\MarketEventGenerator;
use Database\Seeders\PirateFactionSeeder;
use Database\Seeders\PrecursorShipSeeder;
use Database\Seeders\ShipTypesSeeder;
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
                            {--skip-pirates : Skip pirate distribution (enabled by default)}
                            {--skip-inventory : Skip trading hub inventory population}
                            {--skip-mirror : Skip mirror universe creation (enabled by default)}
                            {--skip-precursors : Skip precursor ship spawning (enabled by default)}
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
        $this->info('Configuration:');
        $this->line("  Name: {$name}".($this->argument('name') ? '' : ' (auto-generated)'));
        $this->line("  Dimensions: {$width}x{$height}");
        $this->line("  Stars: {$starCount}");
        $this->line("  Density: {$density}");
        $this->line("  Sector Grid: {$gridSize}x{$gridSize}");
        $this->newLine();

        // Step 1: Create Galaxy
        $this->step(0, 'Creating Galaxy');
        $this->galaxy = $this->createGalaxy($name, $width, $height, $density);
        $this->success("Galaxy created: {$this->galaxy->name} (ID: {$this->galaxy->id})");

        $this->step(1, 'Verifying Database Prerequisites');
        $this->seedPrerequisites();

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

        // Step 5: Designate Inhabited Systems (33-50% of stars)
        $this->step(5, 'Designating Inhabited Star Systems');
        $inhabitedPercentage = config('game_config.galaxy.inhabited_percentage', 0.40);
        $this->info('  Marking '.($inhabitedPercentage * 100).'% of star systems as inhabited...');
        $this->callCommand('galaxy:designate-inhabited', [
            'galaxy' => $this->galaxy->id,
            '--percentage' => $inhabitedPercentage,
        ]);

        // Step 6: Generate Warp Gates for Inhabited Systems Only (Always Incremental)
        if (! $this->option('skip-gates')) {
            $this->step(6, 'Generating Warp Gate Network for Inhabited Systems');
            $this->info('  Connecting inhabited systems via warp gates...');
            $this->info('  Uninhabited systems remain isolated for exploration...');

            // Auto-calculate adjacency threshold based on galaxy dimensions
            // Formula: max dimension / 15 gives good connectivity
            $adjacencyThreshold = max($width, $height) / 15;
            $this->info("  Auto-calculated adjacency threshold: {$adjacencyThreshold}");

            $this->callCommand('galaxy:generate-gates', [
                'galaxy' => $this->galaxy->id,
                '--incremental' => true,
                '--regenerate' => true,
                '--adjacency' => $adjacencyThreshold,
            ]);
        } else {
            $this->warn('⊘ Skipping warp gate generation');
        }

        // Step 7: Generate Trading Hubs at 50-80% of Inhabited Systems
        if (! $this->option('skip-gates')) {
            $this->step(7, 'Generating Trading Hubs at Inhabited Systems');
            $hubProbability = config('game_config.inhabited_systems.guaranteed_services.trading_hub', 0.65);
            $this->info('  Spawning trading hubs at '.($hubProbability * 100).'% of inhabited systems...');
            $this->callCommand('trading:generate-hubs', [
                'galaxy' => $this->galaxy->id,
                '--min-gates' => 1,
                '--hub-probability' => $hubProbability,
                '--min-spacing' => 100,
            ]);
        } else {
            $this->warn('⊘ Skipping trading hub generation (requires warp gates)');
        }

        // Step 8: Populate Trading Hub Inventory
        if (! $this->option('skip-inventory')) {
            $this->step(8, 'Populating Trading Hub Inventory');
            $this->callCommand('trading-hub:populate-inventory', [
                'galaxy' => $this->galaxy->id,
                '--regenerate' => true,
            ]);
        } else {
            $this->warn('⊘ Skipping trading hub inventory population');
        }

        // Step 9: Generate Stellar Cartographer Shops
        $this->step(9, 'Establishing Stellar Cartographer Shops');
        $spawnRate = config('game_config.star_charts.spawn_rate', 0.3);
        $this->info('  Spawning cartographers at '.($spawnRate * 100).'% of trading hubs...');
        $this->callCommand('cartography:generate-shops', [
            'galaxy' => $this->galaxy->id,
            '--spawn-rate' => $spawnRate,
            '--regenerate' => true,
        ]);

        // Step 10: Generate Initial Market Events
        $this->step(10, 'Generating Initial Market Events');
        $this->generateInitialMarketEvents();

        // Step 11: Spawn Precursor Ship in Prime Galaxy (ENABLED BY DEFAULT - skip with --skip-precursors)
        if (! $this->option('skip-precursors')) {
            $this->step(11, '🛸 Spawning Precursor Ship in the Void 🛸');
            $this->info('  Hiding ancient vessel in interstellar space...');

            $seeder = app(PrecursorShipSeeder::class);
            $seeder->setCommand($this);
            $seeder->seedPrecursorShip($this->galaxy);

            $this->info('✅ Precursor Ship hidden in the void');
        } else {
            $this->warn('⊘ Precursor ship spawning skipped');
        }

        // Step 12: Distribute Pirates (ENABLED BY DEFAULT - skip with --skip-pirates)
        if (! $this->option('skip-pirates')) {
            $this->step(12, 'Distributing Pirates to Warp Lanes');
            $this->callCommand('galaxy:distribute-pirates', [
                'galaxy' => $this->galaxy->id,
            ]);

            // Step 12b: Distribute Mobile Pirate Bands (sector-based)
            $this->info('');
            $this->info('  Distributing mobile pirate bands to sectors...');
            $this->callCommand('galaxy:distribute-pirate-bands', [
                'galaxy' => $this->galaxy->id,
            ]);
        } else {
            $this->warn('⊘ Pirate distribution skipped');
        }

        // Step 13: Create Mirror Universe (ENABLED BY DEFAULT - skip with --skip-mirror)
        if (! $this->option('skip-mirror') && config('game_config.mirror_universe.enabled', true)) {
            $this->step(13, '🌌 Creating Mirror Universe (High-Risk, High-Reward) 🌌');
            $this->callCommand('galaxy:create-mirror', array_filter([
                'galaxy' => $this->galaxy->id,
                '--poi' => $this->option('mirror-poi'),

            ]));

            // Step 14: Spawn Precursor Ship in Mirror Universe
            if (! $this->option('skip-precursors')) {
                $mirrorGalaxy = $this->galaxy->getPairedGalaxy();

                if ($mirrorGalaxy) {
                    $this->newLine();
                    $this->info('🛸 Spawning Precursor Ship in Mirror Universe 🛸');

                    $seeder = app(PrecursorShipSeeder::class);
                    $seeder->setCommand($this);
                    $seeder->seedPrecursorShip($mirrorGalaxy);

                    $this->info('✅ Mirror universe Precursor Ship hidden');
                }
            }
        } else {
            if (! config('game_config.mirror_universe.enabled', true)) {
                $this->warn('⊘ Mirror universe disabled in config');
            } else {
                $this->warn('⊘ Mirror universe skipped');
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

    private function generateGalaxyName(): string
    {
        $prefixes = [
            'Andromeda', 'Centaurus', 'Cygnus', 'Draco', 'Fornax',
            'Hydra', 'Lyra', 'Orion', 'Perseus', 'Phoenix',
            'Scorpius', 'Taurus', 'Ursa', 'Vela', 'Virgo',
            'Nova', 'Nebula', 'Stellar', 'Cosmic', 'Celestial',
            'Galactic', 'Astral', 'Ethereal', 'Quantum', 'Void',
        ];

        $descriptors = [
            'Expanse', 'Rift', 'Cluster', 'Nexus', 'Reach',
            'Frontier', 'Sector', 'Domain', 'Territory', 'Quadrant',
            'Region', 'Zone', 'Array', 'Collective', 'Network',
            'Void', 'Shroud', 'Abyss', 'Horizon', 'Infinity',
        ];

        $suffixes = [
            'Prime', 'Alpha', 'Beta', 'Gamma', 'Delta',
            'Major', 'Minor', 'Central', 'Outer', 'Inner',
            'Rising', 'Ascending', 'Eternal', 'Ancient', 'New',
            'Lost', 'Hidden', 'Unknown', 'Distant', 'Remote',
        ];

        // Generate different name patterns
        $pattern = rand(1, 4);

        return match ($pattern) {
            1 => $prefixes[array_rand($prefixes)].' '.$descriptors[array_rand($descriptors)],
            2 => $prefixes[array_rand($prefixes)].' '.$suffixes[array_rand($suffixes)],
            3 => $prefixes[array_rand($prefixes)].' '.$descriptors[array_rand($descriptors)].' '.$suffixes[array_rand($suffixes)],
            4 => 'The '.$prefixes[array_rand($prefixes)].' '.$descriptors[array_rand($descriptors)],
        };
    }

    private function step(int $number, string $description): void
    {
        $this->newLine();
        $this->info('═══════════════════════════════════════════════════════════════');
        $this->info("  STEP {$number}: {$description}");
        $this->info('═══════════════════════════════════════════════════════════════');
        $this->newLine();
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

    private function success(string $message): void
    {
        $this->newLine();
        $this->info("✅ {$message}");
        $this->newLine();
    }

    private function seedPrerequisites(): void
    {
        $seeded = [];
        $skipped = [];

        // Check and seed Minerals
        if (Mineral::count() === 0) {
            $this->info('  Seeding minerals...');
            Artisan::call('db:seed', ['--class' => 'MineralSeeder'], $this->output);
            $seeded[] = 'Minerals';
        } else {
            $skipped[] = 'Minerals (already exist)';
        }

        // Check and seed Ship Types
        if (Ship::count() === 0) {
            $this->info('  Seeding ship types...');
            $seeder = new ShipTypesSeeder;
            $seeder->run();
            $seeder->generateShips($this->galaxy, $this);
            $seeded[] = 'Ship Types';
        } else {
            $skipped[] = 'Ship Types (already exist)';
        }

        // Check and seed Upgrade Plans
        if (Plan::count() === 0) {
            $this->info('  Seeding upgrade plans...');
            Artisan::call('db:seed', ['--class' => 'PlansSeeder'], $this->output);
            $seeded[] = 'Upgrade Plans';
        } else {
            $skipped[] = 'Upgrade Plans (already exist)';
        }

        // Check and seed Pirate Factions
        if (PirateFaction::count() === 0) {
            $this->info('  Seeding pirate factions...');
            $seeder = new PirateFactionSeeder;
            $seeder->run();
            $seeder->generatePirateFactions($this->galaxy, $this);
            $seeded[] = 'Pirate Factions';
        } else {
            $skipped[] = 'Pirate Factions (already exist)';
        }

        // Check and seed Pirate Captains
        if (PirateCaptain::count() === 0) {
            $this->info('  Seeding pirate captains...');
            Artisan::call('db:seed', ['--class' => 'PirateCaptainSeeder'], $this->output);
            $seeded[] = 'Pirate Captains';
        } else {
            $skipped[] = 'Pirate Captains (already exist)';
        }

        // Note: Precursor Ships are now seeded per-galaxy in steps 11 and 14 (not globally)

        $this->newLine();
        if (! empty($seeded)) {
            $this->info('✅ Seeded: '.implode(', ', $seeded));
        }
        if (! empty($skipped)) {
            $this->line('⊘ Skipped: '.implode(', ', $skipped));
        }
        $this->newLine();
    }

    private function callCommand(string $command, array $parameters = []): void
    {
        // Call the command and show its output
        Artisan::call($command, $parameters, $this->output);
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
            $events = MarketEvent::where('is_active', true)
                ->orderBy('created_at', 'desc')
                ->take($generated)
                ->get();

            foreach ($events as $event) {
                $mineralName = $event->mineral ? $event->mineral->name : 'All Minerals';
                $this->line("  • {$event->event_type->getDisplayName()}: {$mineralName} ({$event->price_multiplier}x for {$event->getDurationString()})");
            }
        }
    }

    private function displaySummary(): void
    {
        // Reload galaxy with relationships
        $this->galaxy->refresh();

        $starCount = $this->galaxy->pointsOfInterest()
            ->where('type', PointOfInterestType::STAR)
            ->count();

        $inhabitedStars = $this->galaxy->pointsOfInterest()
            ->stars()
            ->inhabited()
            ->count();

        $uninhabitedStars = $this->galaxy->pointsOfInterest()
            ->stars()
            ->uninhabited()
            ->count();

        $poiCount = $this->galaxy->pointsOfInterest()->count();
        $sectorCount = $this->galaxy->sectors()->count();
        $gateCount = $this->galaxy->warpGates()->count();

        $pirateCount = WarpLanePirate::whereHas('warpGate', function ($query) {
            $query->where('galaxy_id', $this->galaxy->id);
        })->count();

        $tradingHubCount = TradingHub::whereHas('pointOfInterest', function ($query) {
            $query->where('galaxy_id', $this->galaxy->id);
        })->where('is_active', true)->count();

        $shipInventoryCount = TradingHubShip::whereHas('tradingHub.pointOfInterest', function ($query) {
            $query->where('galaxy_id', $this->galaxy->id);
        })->sum('quantity');

        $marketEventCount = MarketEvent::where('is_active', true)
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
            ['  ├─ Inhabited', number_format($inhabitedStars)],
            ['  └─ Uninhabited', number_format($uninhabitedStars)],
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
}
