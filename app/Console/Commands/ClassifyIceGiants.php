<?php

namespace App\Console\Commands;

use App\Models\PointOfInterest;
use App\Models\Star;
use Illuminate\Console\Command;

class ClassifyIceGiants extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'galaxy:classify-ice-giants
                          {--min-ice-giants=1 : Minimum ice giants per star system}
                          {--max-ice-giants=3 : Maximum ice giants per star system}
                          {--asteroid-chance=50 : Percentage chance of asteroid belt per system}
                          {--quantium-min=3000 : Minimum Quantium deposit size}
                          {--quantium-max=8000 : Maximum Quantium deposit size}
                          {--skip-existing : Skip systems that already have ice giants}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Classify ice giants, generate asteroid belts, and add Quantium deposits';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🪐 Processing Mining Resources (Ice Giants & Asteroid Belts)...');
        $this->newLine();

        $minIceGiants = (int) $this->option('min-ice-giants');
        $maxIceGiants = (int) $this->option('max-ice-giants');
        $asteroidChance = (int) $this->option('asteroid-chance');
        $quantiumMin = (int) $this->option('quantium-min');
        $quantiumMax = (int) $this->option('quantium-max');
        $skipExisting = $this->option('skip-existing');

        $stats = [
            'systems_processed' => 0,
            'systems_skipped' => 0,
            'ice_giants_created' => 0,
            'asteroid_belts_created' => 0,
            'quantium_deposits_added' => 0,
        ];

        // Get all stars with POIs
        $stars = Star::with('pointsOfInterest')->get();

        $progressBar = $this->output->createProgressBar($stars->count());
        $progressBar->start();

        foreach ($stars as $star) {
            // Count existing ice giants in this system
            $existingIceGiants = $star->pointsOfInterest()
                ->where('planet_class', 'ice_giant')
                ->count();

            // Skip if requested and system already has ice giants
            if ($skipExisting && $existingIceGiants > 0) {
                $stats['systems_skipped']++;
                $progressBar->advance();
                continue;
            }

            // Process ice giants
            $gasGiants = $star->pointsOfInterest()
                ->where('type', 'gas_giant')
                ->get();

            if ($gasGiants->isNotEmpty()) {
                // Determine how many ice giants this system should have
                $targetCount = rand($minIceGiants, $maxIceGiants);
                $needed = max(0, $targetCount - $existingIceGiants);

                if ($needed > 0) {
                    // Convert some gas giants to ice giants
                    $candidates = $gasGiants->where('planet_class', '!=', 'ice_giant')
                        ->take($needed);

                    foreach ($candidates as $gasGiant) {
                        $this->convertToIceGiant($gasGiant, $quantiumMin, $quantiumMax);
                        $stats['ice_giants_created']++;
                        $stats['quantium_deposits_added']++;
                    }
                }
            }

            // Generate asteroid belt (chance-based)
            if (rand(1, 100) <= $asteroidChance) {
                // Check if system already has an asteroid belt
                $hasAsteroidBelt = $star->pointsOfInterest()
                    ->where('type', 'asteroid_belt')
                    ->exists();

                if (!$hasAsteroidBelt) {
                    $this->createAsteroidBelt($star, $quantiumMin, $quantiumMax);
                    $stats['asteroid_belts_created']++;
                    $stats['quantium_deposits_added']++;
                }
            }

            $stats['systems_processed']++;
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        // Display results
        $this->info('✅ Mining Resource Generation Complete!');
        $this->newLine();
        $this->line('═══════════════════════════════════════════════════');
        $this->line('                    RESULTS                        ');
        $this->line('═══════════════════════════════════════════════════');
        $this->line("Systems Processed:        {$stats['systems_processed']}");
        if ($skipExisting && $stats['systems_skipped'] > 0) {
            $this->line("Systems Skipped:          {$stats['systems_skipped']}");
        }
        $this->line("Ice Giants Created:       {$stats['ice_giants_created']}");
        $this->line("Asteroid Belts Created:   {$stats['asteroid_belts_created']}");
        $this->line("Quantium Deposits Added:  {$stats['quantium_deposits_added']}");
        $this->line('═══════════════════════════════════════════════════');

        $this->newLine();
        $this->info('💡 Mining resources are ready for extraction!');

        return Command::SUCCESS;
    }

    /**
     * Convert a gas giant to an ice giant with Quantium deposits
     */
    private function convertToIceGiant(
        PointOfInterest $poi,
        int $quantiumMin,
        int $quantiumMax
    ): void {
        // Set as ice giant
        $poi->planet_class = 'ice_giant';

        // Set appropriate attributes for ice giants
        $poi->temperature = rand(-220, -150); // Very cold
        $poi->atmosphere_type = 'hydrogen_helium';
        $poi->atmosphere_density = rand(150, 300) / 100; // 1.5 to 3.0x Earth

        // Add Quantium deposits
        $mineralDeposits = $poi->mineral_deposits ?? [];
        $quantiumDeposit = rand($quantiumMin, $quantiumMax);

        $mineralDeposits['Quantium'] = [
            'size' => $quantiumDeposit,
            'richness' => $this->determineRichness($quantiumDeposit, $quantiumMin, $quantiumMax),
            'accessibility' => 'orbital_mining_required',
        ];

        // Optionally add other minerals
        if (rand(1, 100) <= 40) { // 40% chance of helium-3
            $mineralDeposits['Helium-3'] = [
                'size' => rand(1000, 3000),
                'richness' => 'moderate',
                'accessibility' => 'orbital_mining_required',
            ];
        }

        $poi->mineral_deposits = $mineralDeposits;

        // Set habitability
        $poi->habitability_score = 0.0; // Not habitable
        $poi->is_colonizable = false; // Can't colonize ice giants

        $poi->save();
    }

    /**
     * Create an asteroid belt in a star system
     */
    private function createAsteroidBelt(
        Star $star,
        int $quantiumMin,
        int $quantiumMax
    ): void {
        // Generate random position in the system
        $distance = rand(200, 600); // Distance from star
        $angle = rand(0, 360);
        $x = $star->x + ($distance * cos(deg2rad($angle)));
        $y = $star->y + ($distance * sin(deg2rad($angle)));

        // Create asteroid belt POI
        $asteroidBelt = PointOfInterest::create([
            'uuid' => \Illuminate\Support\Str::uuid(),
            'star_id' => $star->id,
            'galaxy_id' => $star->galaxy_id,
            'name' => $star->name . ' Asteroid Belt',
            'type' => 'asteroid_belt',
            'x' => $x,
            'y' => $y,
            'is_active' => true,
        ]);

        // Add mineral deposits
        $mineralDeposits = [];

        // Common minerals (always present)
        $mineralDeposits['Iron'] = [
            'size' => rand(5000, 15000),
            'richness' => 'abundant',
            'accessibility' => 'orbital_mining_required',
        ];

        $mineralDeposits['Nickel'] = [
            'size' => rand(3000, 8000),
            'richness' => 'rich',
            'accessibility' => 'orbital_mining_required',
        ];

        // 30% chance of Quantium in asteroid belts (less than ice giants)
        if (rand(1, 100) <= 30) {
            $quantiumDeposit = rand((int)($quantiumMin * 0.5), (int)($quantiumMax * 0.7)); // Smaller deposits
            $mineralDeposits['Quantium'] = [
                'size' => $quantiumDeposit,
                'richness' => $this->determineRichness($quantiumDeposit, $quantiumMin, $quantiumMax),
                'accessibility' => 'orbital_mining_required',
            ];
        }

        // Rare minerals (20% chance each)
        $rareMinerals = ['Platinum', 'Gold', 'Titanium', 'Palladium'];
        foreach ($rareMinerals as $mineral) {
            if (rand(1, 100) <= 20) {
                $mineralDeposits[$mineral] = [
                    'size' => rand(500, 2000),
                    'richness' => 'moderate',
                    'accessibility' => 'orbital_mining_required',
                ];
            }
        }

        $asteroidBelt->mineral_deposits = $mineralDeposits;
        $asteroidBelt->planet_class = 'asteroid_field';
        $asteroidBelt->habitability_score = 0.0;
        $asteroidBelt->is_colonizable = false;
        $asteroidBelt->save();
    }

    /**
     * Determine richness based on deposit size
     */
    private function determineRichness(int $size, int $min, int $max): string
    {
        $range = $max - $min;
        $relative = ($size - $min) / $range;

        return match(true) {
            $relative >= 0.8 => 'abundant',
            $relative >= 0.6 => 'rich',
            $relative >= 0.4 => 'moderate',
            $relative >= 0.2 => 'poor',
            default => 'trace',
        };
    }
}
