<?php

namespace App\Console\Commands;

use App\Enums\Galaxy\GalaxyDistributionMethod;
use App\Enums\Galaxy\GalaxyRandomEngine;
use App\Enums\Galaxy\GalaxyStatus;
use App\Models\Galaxy;
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
                            {name : The name of the galaxy}
                            {--width=1000 : Width of the galaxy}
                            {--height=1000 : Height of the galaxy}
                            {--stars=1000 : Number of stars to generate}
                            {--density=scatter : Distribution method (scatter, poisson, cluster)}
                            {--grid-size=10 : Sector grid size (default 10x10)}
                            {--skip-gates : Skip warp gate generation}
                            {--skip-pirates : Skip pirate distribution}
                            {--skip-inventory : Skip trading hub inventory population}';

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
        $this->info('  GALAXY INITIALIZATION - COMPLETE SETUP');
        $this->info('════════════════════════════════════════════════════════════════');
        $this->newLine();

        $name = $this->argument('name');
        $width = (int) $this->option('width');
        $height = (int) $this->option('height');
        $starCount = (int) $this->option('stars');
        $density = $this->option('density');
        $gridSize = (int) $this->option('grid-size');

        // Display configuration
        $this->info("Configuration:");
        $this->line("  Name: {$name}");
        $this->line("  Dimensions: {$width}x{$height}");
        $this->line("  Stars: {$starCount}");
        $this->line("  Density: {$density}");
        $this->line("  Sector Grid: {$gridSize}x{$gridSize}");
        $this->newLine();

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

        // Step 3: Generate Sectors
        $this->step(3, "Generating Sector Grid ({$gridSize}x{$gridSize})");
        $this->callCommand('galaxy:generate-sectors', [
            'galaxy' => $this->galaxy->id,
            '--grid-size' => $gridSize,
        ]);

        // Step 4: Generate Warp Gates
        if (!$this->option('skip-gates')) {
            $this->step(4, 'Generating Warp Gates');

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

        // Step 5: Distribute Pirates
        if (!$this->option('skip-pirates')) {
            $this->step(5, 'Distributing Pirates to Warp Lanes');
            $this->callCommand('galaxy:distribute-pirates', [
                'galaxy' => $this->galaxy->id,
            ]);
        } else {
            $this->warn('⊘ Skipping pirate distribution');
        }

        // Step 6: Populate Trading Hub Inventory
        if (!$this->option('skip-inventory')) {
            $this->step(6, 'Populating Trading Hub Inventory');
            $this->callCommand('trading-hub:populate-inventory', [
                '--regenerate' => true,
            ]);
        } else {
            $this->warn('⊘ Skipping trading hub inventory population');
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

        $this->table(
            ['Component', 'Count'],
            [
                ['Galaxy ID', $this->galaxy->id],
                ['Galaxy Name', $this->galaxy->name],
                ['Dimensions', "{$this->galaxy->width}x{$this->galaxy->height}"],
                ['Stars', number_format($starCount)],
                ['Total POIs', number_format($poiCount)],
                ['Sectors', number_format($sectorCount)],
                ['Warp Gates', number_format($gateCount)],
                ['Pirate Encounters', number_format($pirateCount)],
                ['Trading Hubs', number_format($tradingHubCount)],
                ['Ships in Stock', number_format($shipInventoryCount)],
            ]
        );

        $this->newLine();
        $this->info("🌌 Galaxy '{$this->galaxy->name}' is ready for exploration!");
        $this->info("   View it with: php artisan galaxy:view {$this->galaxy->id}");
        $this->newLine();
    }
}
