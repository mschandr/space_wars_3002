<?php

namespace App\Console\Commands;

use App\Models\Galaxy;
use App\Services\Trading\MineralSourceMapper;
use Illuminate\Console\Command;

class AssignMineralProductionCommand extends Command
{
    protected $signature = 'trading:assign-production
                            {galaxy? : Galaxy ID or name}
                            {--reassign : Reassign production even if already set}';

    protected $description = 'Assign mineral production capabilities to POIs based on their type';

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
            $galaxy = Galaxy::find($galaxyId);
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

        $this->info("Assigning mineral production for galaxy: {$galaxy->name}");

        $pois = $galaxy->pointsOfInterest;
        $totalPois = $pois->count();

        if ($totalPois === 0) {
            $this->error("Galaxy '{$galaxy->name}' has no POIs.");
            return Command::FAILURE;
        }

        $reassign = $this->option('reassign');
        $updated = 0;
        $skipped = 0;
        $productionByType = [];

        $progressBar = $this->output->createProgressBar($totalPois);
        $progressBar->start();

        foreach ($pois as $poi) {
            // Skip if already assigned and not reassigning
            if (!$reassign && isset($poi->attributes['produces'])) {
                $skipped++;
                $progressBar->advance();
                continue;
            }

            // Assign mineral production based on POI type
            $produces = MineralSourceMapper::assignMineralProduction($poi->type);

            // Update POI attributes
            $attributes = $poi->attributes ?? [];
            $attributes['produces'] = $produces;
            $poi->attributes = $attributes;
            $poi->save();

            $updated++;

            // Track statistics
            $typeKey = $poi->type->value;
            if (!isset($productionByType[$typeKey])) {
                $productionByType[$typeKey] = [
                    'count' => 0,
                    'minerals' => [],
                ];
            }
            $productionByType[$typeKey]['count']++;
            $productionByType[$typeKey]['minerals'] = array_merge(
                $productionByType[$typeKey]['minerals'],
                $produces
            );

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        $this->info("✅ Mineral production assignment complete!");
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total POIs', $totalPois],
                ['Updated', $updated],
                ['Skipped (already assigned)', $skipped],
            ]
        );

        // Show production by POI type
        $this->newLine();
        $this->info('Production by POI Type:');
        $typeTable = [];

        foreach ($productionByType as $type => $data) {
            $uniqueMinerals = array_unique($data['minerals']);
            $typeTable[] = [
                $type,
                $data['count'],
                count($uniqueMinerals),
                implode(', ', array_slice($uniqueMinerals, 0, 5)) . (count($uniqueMinerals) > 5 ? '...' : ''),
            ];
        }

        $this->table(
            ['POI Type', 'Count', 'Unique Minerals', 'Sample Minerals'],
            $typeTable
        );

        $this->newLine();
        $this->info('💡 Tip: Now regenerate trading hubs to use proximity-based pricing:');
        $this->info("   php artisan trading:generate-hubs {$galaxy->id} --regenerate");

        return Command::SUCCESS;
    }
}
