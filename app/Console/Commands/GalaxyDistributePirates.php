<?php

namespace App\Console\Commands;

use App\Models\Galaxy;
use App\Models\PirateCaptain;
use App\Models\WarpGate;
use App\Models\WarpLanePirate;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class GalaxyDistributePirates extends Command
{
    protected $signature = 'galaxy:distribute-pirates
                            {galaxy : Galaxy ID or name}
                            {--percentage=10 : Percentage of lanes to assign pirates (1-100)}
                            {--regenerate : Remove existing pirates and regenerate}';

    protected $description = 'Distribute pirates across warp lanes in a galaxy';

    private int $piratesCreated = 0;
    private int $piratesSkipped = 0;

    public function handle(): int
    {
        $galaxyIdentifier = $this->argument('galaxy');

        // Find galaxy
        $galaxy = is_numeric($galaxyIdentifier)
            ? Galaxy::find($galaxyIdentifier)
            : Galaxy::where('name', 'like', "%{$galaxyIdentifier}%")->first();

        if (!$galaxy) {
            $this->error("Galaxy not found: {$galaxyIdentifier}");
            return Command::FAILURE;
        }

        // Get total gate count
        $totalGates = WarpGate::where('galaxy_id', $galaxy->id)
            ->where('is_hidden', false)
            ->where('status', 'active')
            ->count();

        if ($totalGates === 0) {
            $this->error("Galaxy '{$galaxy->name}' has no active warp gates.");
            return Command::FAILURE;
        }

        // Get captains
        $captains = PirateCaptain::all();
        if ($captains->isEmpty()) {
            $this->error('No pirate captains found. Run PirateCaptainSeeder first.');
            $this->info('Run: php artisan db:seed --class=PirateCaptainSeeder');
            return Command::FAILURE;
        }

        // Delete existing pirates if regenerating
        if ($this->option('regenerate')) {
            $existingCount = WarpLanePirate::whereHas('warpGate', function ($query) use ($galaxy) {
                $query->where('galaxy_id', $galaxy->id);
            })->count();

            if ($existingCount > 0) {
                WarpLanePirate::whereHas('warpGate', function ($query) use ($galaxy) {
                    $query->where('galaxy_id', $galaxy->id);
                })->delete();
                $this->info("Deleted {$existingCount} existing pirate encounters");
            }
        }

        $this->info("Distributing pirates across galaxy: {$galaxy->name}");
        $this->info("Total warp gates: {$totalGates}");
        $this->newLine();

        // Calculate target count (only one direction per lane pair)
        $percentage = max(1, min(100, (int)$this->option('percentage')));
        $bidirectionalGates = $totalGates / 2; // Gates come in pairs
        $targetPirateCount = max(1, (int)round($bidirectionalGates * ($percentage / 100)));

        $this->info("Target pirate encounters: {$targetPirateCount} ({$percentage}% of {$bidirectionalGates} lanes)");
        $this->newLine();

        // Process gates in chunks
        $this->distributePiratesIncremental($galaxy, $captains, $targetPirateCount);

        // Show summary
        $this->newLine();
        $this->info("✅ Pirate distribution complete!");
        $this->newLine();

        $totalPirates = WarpLanePirate::whereHas('warpGate', function ($query) use ($galaxy) {
            $query->where('galaxy_id', $galaxy->id);
        })->count();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Target Pirates', $targetPirateCount],
                ['Pirates Created', $this->piratesCreated],
                ['Pirates Skipped', $this->piratesSkipped],
                ['Total Active Pirates', $totalPirates],
                ['Percentage of Lanes', round(($totalPirates / $bidirectionalGates) * 100, 1) . '%'],
            ]
        );

        return Command::SUCCESS;
    }

    private function distributePiratesIncremental(Galaxy $galaxy, $captains, int $targetCount): void
    {
        $this->piratesCreated = 0;
        $this->piratesSkipped = 0;
        $chunkSize = 100;
        $gatesProcessed = 0;

        // Get unique gate pairs (source_id < destination_id to avoid duplicates)
        WarpGate::where('galaxy_id', $galaxy->id)
            ->where('is_hidden', false)
            ->where('status', 'active')
            ->whereColumn('source_poi_id', '<', 'destination_poi_id') // Only one direction
            ->inRandomOrder() // Randomize for distribution
            ->chunk($chunkSize, function ($gateChunk) use (&$gatesProcessed, $captains, $targetCount, $galaxy) {
                foreach ($gateChunk as $gate) {
                    // Stop if we've reached target
                    if ($this->piratesCreated >= $targetCount) {
                        return false; // Stop chunking
                    }

                    $gatesProcessed++;

                    // Check if this gate already has pirates
                    if (WarpLanePirate::where('warp_gate_id', $gate->id)->exists()) {
                        $this->piratesSkipped++;
                        continue;
                    }

                    // Create pirate encounter
                    $captain = $captains->random();
                    $fleetSize = rand(1, 4);
                    $difficultyTier = rand(1, 5);

                    WarpLanePirate::create([
                        'uuid' => Str::uuid(),
                        'warp_gate_id' => $gate->id,
                        'captain_id' => $captain->id,
                        'fleet_size' => $fleetSize,
                        'difficulty_tier' => $difficultyTier,
                        'is_active' => true,
                    ]);

                    $this->piratesCreated++;

                    // Show progress every 10 pirates
                    if ($this->piratesCreated % 10 === 0) {
                        $this->output->write(
                            "\r\033[K" .
                            "Progress: {$this->piratesCreated}/{$targetCount} pirates created | " .
                            "Gates checked: {$gatesProcessed} | " .
                            "Skipped: {$this->piratesSkipped}"
                        );
                    }
                }
            });

        // Final progress update
        $this->output->write(
            "\r\033[K" .
            "Progress: {$this->piratesCreated}/{$targetCount} pirates created | " .
            "Gates checked: {$gatesProcessed} | " .
            "Skipped: {$this->piratesSkipped}"
        );
    }
}
