<?php

namespace App\Console\Commands;

use App\Models\Galaxy;
use App\Models\Mineral;
use App\Services\Trading\TradingHubGenerator;
use Illuminate\Console\Command;

class TradingHubGenerateCommand extends Command
{
    protected $signature = 'trading:generate-hubs
                            {galaxy? : Galaxy ID or name}
                            {--min-gates=3 : Minimum number of gates required for a hub}
                            {--salvage-probability=0.3 : Probability of hub having a salvage yard (0.0-1.0)}
                            {--hub-probability=0.25 : Probability of spawning a hub at eligible locations (0.0-1.0)}
                            {--min-spacing=100 : Minimum distance between trading hubs}
                            {--regenerate : Delete existing hubs and regenerate}';

    protected $description = 'Generate trading hubs at warp gate intersections in a galaxy';

    public function handle(): int
    {
        $galaxyIdentifier = $this->argument('galaxy');

        // If no galaxy specified, prompt for one
        if (!$galaxyIdentifier) {
            $galaxies = Galaxy::all();

            if ($galaxies->isEmpty()) {
                $this->error('No galaxies found. Create a galaxy first.');
                return Command::FAILURE;
            }

            $choices = $galaxies->mapWithKeys(function ($galaxy) {
                return [$galaxy->id => "{$galaxy->name} (ID: {$galaxy->id})"];
            })->toArray();

            $galaxyId = $this->choice('Select a galaxy', $choices);
            $galaxy   = Galaxy::find($galaxyId);
        } else {
            // Try to find by ID first, then by name
            $galaxy = is_numeric($galaxyIdentifier)
                ? Galaxy::find($galaxyIdentifier)
                : Galaxy::where('name', $galaxyIdentifier)->first();
        }

        if (!$galaxy) {
            $this->error("Galaxy not found: {$galaxyIdentifier}");
            return Command::FAILURE;
        }

        // Check if galaxy has warp gates
        $gateCount = $galaxy->warpGates()->count();

        if ($gateCount < 2) {
            $this->error("Galaxy '{$galaxy->name}' must have warp gates before generating trading hubs.");
            $this->info("Run: php artisan galaxy:generate-gates {$galaxy->id}");
            return Command::FAILURE;
        }

        // Check if minerals exist
        $mineralCount = Mineral::count();
        if ($mineralCount === 0) {
            $this->warn('No minerals found in the database. Trading hubs will be created but not stocked.');
            $this->info('Run: php artisan db:seed --class=MineralSeeder');

            if (!$this->confirm('Continue anyway?', false)) {
                return Command::FAILURE;
            }
        }

        // Delete existing hubs if regenerating
        if ($this->option('regenerate')) {
            $existingHubs = $galaxy->pointsOfInterest()
                ->whereHas('tradingHub')
                ->get();

            $existingCount = $existingHubs->count();

            if ($existingCount > 0) {
                foreach ($existingHubs as $poi) {
                    $poi->tradingHub()->delete();
                }
                $this->info("Deleted {$existingCount} existing trading hubs");
            }
        }

        // Create generator with options
        $generator = new TradingHubGenerator(
            minGatesForHub: (int)$this->option('min-gates'),
            salvageYardProbability: (float)$this->option('salvage-probability'),
            hubSpawnProbability: (float)$this->option('hub-probability'),
            minHubDistance: (int)$this->option('min-spacing')
        );

        // Generate trading hubs
        $this->info("Generating trading hubs for galaxy: {$galaxy->name}");
        $this->info("Warp gates: {$gateCount}");

        $hubs = $generator->generateHubs($galaxy);

        // Display summary
        $totalHubs = $hubs->count();

        if ($totalHubs === 0) {
            $this->warn('No trading hubs generated. Try lowering --min-gates option.');
            return Command::SUCCESS;
        }

        $hubsByType      = $hubs->groupBy('type');
        $hubsWithSalvage = $hubs->where('has_salvage_yard', true)->count();

        $this->newLine();
        $this->info("✅ Successfully generated trading hub network!");

        $tableData = [
            ['Total Hubs', $totalHubs],
            ['Standard Hubs', $hubsByType->get('standard', collect())->count()],
            ['Major Hubs', $hubsByType->get('major', collect())->count()],
            ['Premium Hubs', $hubsByType->get('premium', collect())->count()],
            ['With Salvage Yards', "{$hubsWithSalvage} (" . round(($hubsWithSalvage / $totalHubs) * 100, 1) . "%)"],
        ];

        if ($mineralCount > 0) {
            $totalInventoryItems = 0;
            foreach ($hubs as $hub) {
                $totalInventoryItems += $hub->inventories()->count();
            }
            $tableData[] = ['Total Inventory Items', $totalInventoryItems];
            $tableData[] = ['Avg Items per Hub', round($totalInventoryItems / $totalHubs, 1)];
        }

        $this->table(['Metric', 'Value'], $tableData);

        // Show individual hubs
        $this->newLine();
        $this->info('Trading Hubs Created:');
        $hubTable = [];
        foreach ($hubs as $hub) {
            $poi        = $hub->pointOfInterest;
            $hubTable[] = [
                $hub->name,
                $hub->type,
                $hub->gate_count,
                $hub->has_salvage_yard ? 'Yes' : 'No',
                $hub->inventories()->count(),
            ];
        }
        $this->table(
            ['Name', 'Type', 'Gates', 'Salvage', 'Minerals'],
            $hubTable
        );

        return Command::SUCCESS;
    }
}
