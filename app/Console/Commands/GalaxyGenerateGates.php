<?php

namespace App\Console\Commands;

use App\Enums\PointsOfInterest\PointOfInterestType;
use App\Models\Galaxy;
use App\Services\WarpGate\WarpGateGenerator;
use Illuminate\Console\Command;

class GalaxyGenerateGates extends Command
{
    protected $signature = 'galaxy:generate-gates
                            {galaxy? : Galaxy ID or name}
                            {--adjacency=1.5 : Distance threshold for adjacent systems}
                            {--hidden-percentage=0.02 : Percentage of gates to mark as hidden (0.0-1.0)}
                            {--max-gates=6 : Maximum gates per star system}
                            {--regenerate : Delete existing gates and regenerate}';

    protected $description = 'Generate warp gates connecting star systems in a galaxy';

    public function handle(): int
    {
        $galaxyIdentifier = $this->argument('galaxy');

        // If no galaxy specified, prompt for one
        if (!$galaxyIdentifier) {
            $galaxies = Galaxy::all();

            if ($galaxies->isEmpty()) {
                $this->error('No galaxies found. Create a galaxy first with galaxy:generate-points');
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

        // Check if galaxy has star systems
        $starCount = $galaxy->pointsOfInterest()
            ->where('type', PointOfInterestType::STAR)
            ->count();

        if ($starCount < 2) {
            $this->error("Galaxy '{$galaxy->name}' must have at least 2 star systems to generate gates.");
            $this->info("Current star count: {$starCount}");
            return Command::FAILURE;
        }

        // Delete existing gates if regenerating
        if ($this->option('regenerate')) {
            $existingCount = $galaxy->warpGates()->count();
            if ($existingCount > 0) {
                $galaxy->warpGates()->delete();
                $this->info("Deleted {$existingCount} existing gates");
            }
        }

        // Create generator with options
        $generator = new WarpGateGenerator(
            adjacencyThreshold: (float) $this->option('adjacency'),
            hiddenGatePercentage: (float) $this->option('hidden-percentage'),
            maxGatesPerSystem: (int) $this->option('max-gates')
        );

        // Generate gates
        $this->info("Generating warp gates for galaxy: {$galaxy->name}");
        $this->info("Star systems: {$starCount}");

        $gates = $generator->generateGates($galaxy);

        // Display summary
        $totalGates = $gates->count();
        $hiddenGates = $gates->where('is_hidden', true)->count();
        $activeGates = $totalGates - $hiddenGates;
        $avgDistance = round($gates->avg('distance'), 2);

        $this->newLine();
        $this->info("✅ Successfully generated warp gate network!");
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Gates', $totalGates],
                ['Active Gates', $activeGates],
                ['Hidden Gates', "{$hiddenGates} (" . round(($hiddenGates / $totalGates) * 100, 1) . "%)"],
                ['Average Distance', $avgDistance],
                ['Star Systems', $starCount],
                ['Avg Gates/System', round($totalGates / $starCount, 1)],
            ]
        );

        return Command::SUCCESS;
    }
}
