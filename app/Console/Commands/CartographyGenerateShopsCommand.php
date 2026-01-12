<?php

namespace App\Console\Commands;

use App\Models\Galaxy;
use App\Models\PointOfInterest;
use App\Models\StellarCartographer;
use Illuminate\Console\Command;

class CartographyGenerateShopsCommand extends Command
{
    protected $signature = 'cartography:generate-shops
                            {galaxy : Galaxy ID}
                            {--spawn-rate=0.3 : Probability of spawning cartographer at each trading hub (0.0-1.0)}
                            {--regenerate : Delete existing cartographers and regenerate}';

    protected $description = 'Generate Stellar Cartographer shops at trading hubs';

    public function handle(): int
    {
        $galaxyId = $this->argument('galaxy');
        $spawnRate = (float) $this->option('spawn-rate');

        // Validate spawn rate
        if ($spawnRate < 0 || $spawnRate > 1) {
            $this->error('Spawn rate must be between 0.0 and 1.0');

            return Command::FAILURE;
        }

        // Find galaxy
        $galaxy = Galaxy::find($galaxyId);
        if (! $galaxy) {
            $this->error("Galaxy not found: {$galaxyId}");

            return Command::FAILURE;
        }

        $this->info("Generating Stellar Cartographer shops for galaxy: {$galaxy->name}");
        $this->info('Spawn rate: '.($spawnRate * 100).'%');
        $this->newLine();

        // Delete existing cartographers if regenerating
        if ($this->option('regenerate')) {
            $existingCount = StellarCartographer::whereHas('pointOfInterest', function ($query) use ($galaxyId) {
                $query->where('galaxy_id', $galaxyId);
            })->count();

            if ($existingCount > 0) {
                StellarCartographer::whereHas('pointOfInterest', function ($query) use ($galaxyId) {
                    $query->where('galaxy_id', $galaxyId);
                })->delete();
                $this->info("Deleted {$existingCount} existing cartographer(s)");
            }
        }

        // Get inhabited systems with trading hubs
        $tradingHubLocations = PointOfInterest::where('galaxy_id', $galaxyId)
            ->inhabited()
            ->where('is_hidden', false)
            ->whereHas('tradingHub', function ($query) {
                $query->where('is_active', true);
            })
            ->get();

        if ($tradingHubLocations->isEmpty()) {
            $this->warn('No inhabited systems with trading hubs found');

            return Command::SUCCESS;
        }

        $this->info("Found {$tradingHubLocations->count()} inhabited trading hub locations");

        // Spawn cartographers at locations based on spawn rate
        $spawnedCount = 0;
        $progressBar = $this->output->createProgressBar($tradingHubLocations->count());
        $progressBar->start();

        foreach ($tradingHubLocations as $location) {
            // Check if already has a cartographer
            if ($location->stellarCartographer) {
                $progressBar->advance();

                continue;
            }

            // Probabilistic spawn
            if ((mt_rand(1, 100) / 100) <= $spawnRate) {
                $this->spawnCartographer($location);
                $spawnedCount++;
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();
        $this->newLine();

        $this->info("✅ Successfully spawned {$spawnedCount} Stellar Cartographer shop(s)");
        $this->newLine();

        // Display summary
        $totalCartographers = StellarCartographer::whereHas('pointOfInterest', function ($query) use ($galaxyId) {
            $query->where('galaxy_id', $galaxyId);
        })->count();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Trading Hub Locations', $tradingHubLocations->count()],
                ['Cartographers Spawned', $spawnedCount],
                ['Total Cartographers', $totalCartographers],
                ['Coverage', round(($totalCartographers / $tradingHubLocations->count()) * 100, 1).'%'],
            ]
        );

        return Command::SUCCESS;
    }

    /**
     * Spawn a cartographer at a location
     */
    private function spawnCartographer(PointOfInterest $location): void
    {
        $name = $this->generateCartographerName();
        $basePrice = config('game_config.star_charts.base_price', 1000);
        $markup = $this->generateMarkup();

        StellarCartographer::create([
            'poi_id' => $location->id,
            'name' => $name,
            'is_active' => true,
            'chart_base_price' => $basePrice,
            'markup_multiplier' => $markup,
        ]);
    }

    /**
     * Generate a random cartographer shop name
     */
    private function generateCartographerName(): string
    {
        $prefixes = config('game_config.cartographer_names.prefixes', ['Star', 'Void', 'Celestial']);
        $suffixes = config('game_config.cartographer_names.suffixes', ['Maps', 'Cartography', 'Navigation']);

        $prefix = $prefixes[array_rand($prefixes)];
        $suffix = $suffixes[array_rand($suffixes)];

        return "{$prefix} {$suffix}";
    }

    /**
     * Generate a markup multiplier with slight variation
     */
    private function generateMarkup(): float
    {
        // Random markup between 0.9 and 1.1 (±10%)
        return round(0.9 + (mt_rand(0, 20) / 100), 2);
    }
}
