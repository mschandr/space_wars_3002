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
        // Resolve galaxy
        $galaxy = $this->resolveGalaxy($this->argument('galaxy'));
        if (! $galaxy) {
            return Command::FAILURE;
        }

        // Validate preconditions
        $gateCount = $galaxy->warpGates()->count();
        if (! $this->validateGates($galaxy, $gateCount)) {
            return Command::FAILURE;
        }

        $mineralCount = Mineral::count();
        if (! $this->validateMinerals($mineralCount)) {
            return Command::FAILURE;
        }

        // Handle regeneration
        $this->handleRegenerate($galaxy);

        // Generate hubs
        $generator = $this->buildGenerator();
        $this->info("Generating trading hubs for galaxy: {$galaxy->name}");
        $this->info("Warp gates: {$gateCount}");

        $hubs = $generator->generateHubs($galaxy);

        // Display results
        $this->displayGenerationResults($hubs, $mineralCount);

        return Command::SUCCESS;
    }

    /**
     * Resolve galaxy from argument or user selection.
     */
    private function resolveGalaxy(?string $galaxyIdentifier): ?Galaxy
    {
        if (! $galaxyIdentifier) {
            $galaxies = Galaxy::all();
            if ($galaxies->isEmpty()) {
                $this->error('No galaxies found. Create a galaxy first.');
                return null;
            }

            $choices = $galaxies->mapWithKeys(fn ($galaxy) => [$galaxy->id => "{$galaxy->name} (ID: {$galaxy->id})"])->toArray();
            $galaxyId = $this->choice('Select a galaxy', $choices);
            return Galaxy::find($galaxyId);
        }

        $galaxy = is_numeric($galaxyIdentifier)
            ? Galaxy::find($galaxyIdentifier)
            : Galaxy::where('name', $galaxyIdentifier)->first();

        if (! $galaxy) {
            $this->error("Galaxy not found: {$galaxyIdentifier}");
        }

        return $galaxy;
    }

    /**
     * Validate that galaxy has sufficient warp gates.
     */
    private function validateGates(Galaxy $galaxy, int $gateCount): bool
    {
        if ($gateCount < 2) {
            $this->error("Galaxy '{$galaxy->name}' must have warp gates before generating trading hubs.");
            $this->info("Run: php artisan galaxy:generate-gates {$galaxy->id}");
            return false;
        }

        return true;
    }

    /**
     * Validate that minerals are seeded in the database.
     */
    private function validateMinerals(int $mineralCount): bool
    {
        if ($mineralCount === 0) {
            $this->warn('No minerals found in the database. Trading hubs will be created but not stocked.');
            $this->info('Run: php artisan db:seed --class=MineralSeeder');

            return $this->confirm('Continue anyway?', false);
        }

        return true;
    }

    /**
     * Delete existing trading hubs if regenerate flag is set.
     */
    private function handleRegenerate(Galaxy $galaxy): void
    {
        if (! $this->option('regenerate')) {
            return;
        }

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

    /**
     * Build trading hub generator with command options.
     */
    private function buildGenerator(): TradingHubGenerator
    {
        return new TradingHubGenerator(
            minGatesForHub: (int) $this->option('min-gates'),
            salvageYardProbability: (float) $this->option('salvage-probability'),
            hubSpawnProbability: (float) $this->option('hub-probability'),
            minHubDistance: (int) $this->option('min-spacing')
        );
    }

    /**
     * Display generation results and statistics.
     */
    private function displayGenerationResults($hubs, int $mineralCount): void
    {
        $totalHubs = $hubs->count();

        if ($totalHubs === 0) {
            $this->warn('No trading hubs generated. Try lowering --min-gates option.');
            return;
        }

        $hubsByType = $hubs->groupBy('type');
        $hubsWithSalvage = $hubs->where('has_salvage_yard', true)->count();

        $this->newLine();
        $this->info('✅ Successfully generated trading hub network!');

        $tableData = [
            ['Total Hubs', $totalHubs],
            ['Standard Hubs', $hubsByType->get('standard', collect())->count()],
            ['Major Hubs', $hubsByType->get('major', collect())->count()],
            ['Premium Hubs', $hubsByType->get('premium', collect())->count()],
            ['With Salvage Yards', "{$hubsWithSalvage} (".round(($hubsWithSalvage / $totalHubs) * 100, 1).'%)'],
        ];

        if ($mineralCount > 0) {
            $hubIds = $hubs->pluck('id');
            $totalInventoryItems = \App\Models\TradingHubInventory::whereIn('trading_hub_id', $hubIds)->count();
            $tableData[] = ['Total Inventory Items', $totalInventoryItems];
            $tableData[] = ['Avg Items per Hub', round($totalInventoryItems / $totalHubs, 1)];
        }

        $this->table(['Metric', 'Value'], $tableData);

        if ($totalHubs <= 50) {
            $this->displayHubDetails($hubs);
        } else {
            $this->newLine();
            $this->info("(Skipping individual hub listing for {$totalHubs} hubs - too many to display)");
        }
    }

    /**
     * Display detailed table of created hubs.
     */
    private function displayHubDetails($hubs): void
    {
        $this->newLine();
        $this->info('Trading Hubs Created:');
        $hubTable = [];
        foreach ($hubs as $hub) {
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
    }
}
