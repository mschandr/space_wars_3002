<?php

namespace App\Console\Commands;

use App\Enums\PointsOfInterest\PointOfInterestType;
use App\Models\Galaxy;
use App\Models\PirateCaptain;
use App\Models\PointOfInterest;
use App\Models\TradingHub;
use App\Models\WarpGate;
use App\Models\WarpLanePirate;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class GalaxyExpandCommand extends Command
{
    protected $signature = 'galaxy:expand
                            {galaxy : Galaxy name or ID}
                            {--stars=5 : Number of star systems to add}
                            {--connect : Auto-connect to nearby systems with warp gates}
                            {--hubs=0 : Number of trading hubs to add}
                            {--pirates=10 : Percentage of new lanes to assign pirates (0-100)}';

    protected $description = 'Expand an existing galaxy by adding new star systems, gates, hubs, and pirates';

    private Galaxy $galaxy;

    private array $newStars = [];

    private array $newGates = [];

    private array $newHubs = [];

    private array $newPirates = [];

    private array $sectors = [];

    public function handle(): int
    {
        $this->info('════════════════════════════════════════════════════════════════');
        $this->info('  GALAXY EXPANSION SYSTEM');
        $this->info('════════════════════════════════════════════════════════════════');
        $this->newLine();

        // Step 1: Load galaxy
        if (! $this->loadGalaxy()) {
            return Command::FAILURE;
        }

        // Step 2: Generate new star systems
        $starsToAdd = max(1, (int) $this->option('stars'));
        $this->info("Generating {$starsToAdd} new star systems...");
        $this->generateStarSystems($starsToAdd);
        $this->newLine();

        // Step 3: Connect with warp gates if requested
        if ($this->option('connect')) {
            $this->info('Connecting new systems with warp gates...');
            $this->connectStarSystems();
            $this->newLine();
        }

        // Step 4: Add trading hubs if requested
        $hubsToAdd = max(0, (int) $this->option('hubs'));
        if ($hubsToAdd > 0) {
            $this->info("Adding {$hubsToAdd} trading hubs...");
            $this->addTradingHubs($hubsToAdd);
            $this->newLine();
        }

        // Step 5: Add pirates if requested
        $piratePercentage = max(0, min(100, (int) $this->option('pirates')));
        if ($piratePercentage > 0 && count($this->newGates) > 0) {
            $this->info("Assigning pirates to {$piratePercentage}% of new lanes...");
            $this->assignPirates($piratePercentage);
            $this->newLine();
        }

        // Step 6: Show summary
        $this->showSummary();

        return Command::SUCCESS;
    }

    private function loadGalaxy(): bool
    {
        $galaxyIdentifier = $this->argument('galaxy');

        // Try to find by ID first
        if (is_numeric($galaxyIdentifier)) {
            $this->galaxy = Galaxy::find($galaxyIdentifier);
        }

        // If not found, try by name
        if (! isset($this->galaxy)) {
            $this->galaxy = Galaxy::where('name', 'like', "%{$galaxyIdentifier}%")->first();
        }

        if (! $this->galaxy) {
            $this->error("Galaxy '{$galaxyIdentifier}' not found.");
            $this->newLine();
            $this->info('Available galaxies:');
            foreach (Galaxy::all() as $galaxy) {
                $this->line("  - {$galaxy->name} (ID: {$galaxy->id})");
            }

            return false;
        }

        $this->info("Expanding galaxy: {$this->galaxy->name}");
        $this->line("  Dimensions: {$this->galaxy->width}x{$this->galaxy->height}");

        $existingStars = $this->galaxy->pointsOfInterest()
            ->where('type', PointOfInterestType::STAR)
            ->count();
        $this->line("  Existing stars: {$existingStars}");

        // Load sectors for auto-assignment
        $this->sectors = $this->galaxy->sectors()->get()->all();
        if (count($this->sectors) > 0) {
            $this->line('  Sectors: '.count($this->sectors).' (auto-assignment enabled)');
        }

        $this->newLine();

        return true;
    }

    private function generateStarSystems(int $count): void
    {
        $existingPois = $this->galaxy->pointsOfInterest()->get();

        for ($i = 0; $i < $count; $i++) {
            // Generate random coordinates with collision avoidance
            $attempts = 0;
            do {
                $x = rand(0, $this->galaxy->width - 1);
                $y = rand(0, $this->galaxy->height - 1);

                // Check for collisions (minimum 10 unit spacing)
                $collision = false;
                foreach ($existingPois as $poi) {
                    $distance = sqrt(pow($x - $poi->x, 2) + pow($y - $poi->y, 2));
                    if ($distance < 10) {
                        $collision = true;
                        break;
                    }
                }

                $attempts++;
            } while ($collision && $attempts < 100);

            if ($attempts >= 100) {
                $starNum = $i + 1;
                $this->warn("Could not find suitable coordinates for star #{$starNum} after 100 attempts. Skipping.");

                continue;
            }

            // Create star system
            $stellarClass = $this->randomStarClass();
            $sectorId = $this->findSectorForCoordinates($x, $y);

            $star = PointOfInterest::create([
                'uuid' => Str::uuid(),
                'galaxy_id' => $this->galaxy->id,
                'sector_id' => $sectorId,
                'name' => $this->generateStarName(),
                'type' => PointOfInterestType::STAR,
                'x' => $x,
                'y' => $y,
                'description' => 'A newly discovered star system in the expanding frontier.',
                'is_hidden' => false,
                'attributes' => [
                    'stellar_class' => $stellarClass,
                    'temperature' => $this->getStarTemperature($stellarClass),
                    'luminosity' => rand(50, 150),
                ],
            ]);

            $this->newStars[] = $star;

            // Generate planets for this star
            $planetCount = rand(2, 5);
            for ($p = 0; $p < $planetCount; $p++) {
                $planet = PointOfInterest::create([
                    'uuid' => Str::uuid(),
                    'galaxy_id' => $this->galaxy->id,
                    'sector_id' => $sectorId,
                    'parent_poi_id' => $star->id,
                    'name' => $this->generatePlanetName(),
                    'type' => PointOfInterestType::PLANET,
                    'x' => $x,
                    'y' => $y,
                    'description' => 'An unexplored planet with unknown potential.',
                    'is_hidden' => false,
                    'attributes' => [
                        'planet_type' => $this->randomPlanetType(),
                        'atmosphere' => $this->randomAtmosphere(),
                        'temperature' => rand(-100, 100),
                    ],
                ]);
            }

            $this->line("  ✓ Created {$star->name} with {$planetCount} planets at ({$x}, {$y})");

            // Add to existing POIs for next collision check
            $existingPois->push($star);
        }

        $this->info('Successfully created '.count($this->newStars).' star systems.');
    }

    private function connectStarSystems(): void
    {
        if (empty($this->newStars)) {
            $this->warn('No new stars to connect.');

            return;
        }

        // Get all existing stars
        $allStars = $this->galaxy->pointsOfInterest()
            ->where('type', PointOfInterestType::STAR)
            ->get();

        foreach ($this->newStars as $newStar) {
            // Find 2-3 nearest stars (excluding self)
            $distances = [];
            foreach ($allStars as $existingStar) {
                if ($existingStar->id === $newStar->id) {
                    continue;
                }

                $distance = sqrt(
                    pow($newStar->x - $existingStar->x, 2) +
                    pow($newStar->y - $existingStar->y, 2)
                );

                $distances[] = [
                    'star' => $existingStar,
                    'distance' => $distance,
                ];
            }

            // Sort by distance and take closest 2-3
            usort($distances, fn ($a, $b) => $a['distance'] <=> $b['distance']);
            $connectionsToMake = rand(2, 3);
            $nearestStars = array_slice($distances, 0, min($connectionsToMake, count($distances)));

            foreach ($nearestStars as $data) {
                $targetStar = $data['star'];

                // Check if connection already exists
                $existingGate = WarpGate::where(function ($query) use ($newStar, $targetStar) {
                    $query->where('source_poi_id', $newStar->id)
                        ->where('destination_poi_id', $targetStar->id);
                })->orWhere(function ($query) use ($newStar, $targetStar) {
                    $query->where('source_poi_id', $targetStar->id)
                        ->where('destination_poi_id', $newStar->id);
                })->first();

                if ($existingGate) {
                    continue;
                }

                // Create bidirectional warp gates
                $distance = $data['distance'];

                // Gate 1: New -> Existing
                $gate1 = WarpGate::create([
                    'uuid' => Str::uuid(),
                    'galaxy_id' => $this->galaxy->id,
                    'source_poi_id' => $newStar->id,
                    'destination_poi_id' => $targetStar->id,
                    'source_x' => $newStar->x,
                    'source_y' => $newStar->y,
                    'dest_x' => $targetStar->x,
                    'dest_y' => $targetStar->y,
                    'distance' => $distance,
                    'is_hidden' => false,
                    'status' => 'active',
                ]);

                // Gate 2: Existing -> New
                $gate2 = WarpGate::create([
                    'uuid' => Str::uuid(),
                    'galaxy_id' => $this->galaxy->id,
                    'source_poi_id' => $targetStar->id,
                    'destination_poi_id' => $newStar->id,
                    'source_x' => $targetStar->x,
                    'source_y' => $targetStar->y,
                    'dest_x' => $newStar->x,
                    'dest_y' => $newStar->y,
                    'distance' => $distance,
                    'is_hidden' => false,
                    'status' => 'active',
                ]);

                $this->newGates[] = $gate1;
                $this->newGates[] = $gate2;

                $this->line("  ✓ Connected {$newStar->name} ↔ {$targetStar->name} (distance: {$distance})");
            }
        }

        $this->info('Successfully created '.count($this->newGates).' warp gates.');
    }

    private function addTradingHubs(int $count): void
    {
        if (empty($this->newStars)) {
            $this->warn('No new stars to add trading hubs to.');

            return;
        }

        // Randomly select stars from the new ones
        $selectedStars = collect($this->newStars)->random(min($count, count($this->newStars)));

        foreach ($selectedStars as $star) {
            // Find a random planet in this system
            $planet = PointOfInterest::where('parent_poi_id', $star->id)
                ->where('type', PointOfInterestType::PLANET)
                ->inRandomOrder()
                ->first();

            if (! $planet) {
                $this->warn("  No planets found for {$star->name}. Skipping hub.");

                continue;
            }

            // Create trading hub
            $hub = TradingHub::create([
                'uuid' => Str::uuid(),
                'poi_id' => $planet->id,
                'name' => $this->generateHubName(),
                'description' => 'A frontier trading outpost serving the expanding colonies.',
                'tier' => rand(1, 3),
                'is_active' => true,
                'attributes' => [
                    'security_level' => rand(3, 8),
                    'facilities' => ['trading', 'repairs', 'refueling'],
                ],
            ]);

            $this->newHubs[] = $hub;
            $this->line("  ✓ Added {$hub->name} at {$planet->name} ({$star->name})");
        }

        $this->info('Successfully created '.count($this->newHubs).' trading hubs.');
    }

    private function assignPirates(int $percentage): void
    {
        if (empty($this->newGates)) {
            $this->warn('No new gates to assign pirates to.');

            return;
        }

        // Get all captains
        $captains = PirateCaptain::all();
        if ($captains->isEmpty()) {
            $this->warn('No pirate captains found. Run PirateCaptainSeeder first.');

            return;
        }

        // Calculate how many gates should have pirates
        $gateCount = count($this->newGates) / 2; // Divide by 2 because gates are bidirectional
        $pirateGateCount = max(1, (int) round($gateCount * ($percentage / 100)));

        // Randomly select gates (only one direction of each pair)
        $selectedGates = collect($this->newGates)
            ->filter(fn ($gate) => $gate->source_poi_id > $gate->destination_poi_id) // Prevent duplicates
            ->random(min($pirateGateCount, $gateCount));

        foreach ($selectedGates as $gate) {
            $captain = $captains->random();

            $pirate = WarpLanePirate::create([
                'uuid' => Str::uuid(),
                'warp_gate_id' => $gate->id,
                'captain_id' => $captain->id,
                'fleet_size' => rand(1, 4),
                'difficulty_tier' => rand(1, 5),
                'is_active' => true,
            ]);

            $this->newPirates[] = $pirate;

            $sourceName = PointOfInterest::find($gate->source_poi_id)->name;
            $destName = PointOfInterest::find($gate->destination_poi_id)->name;
            $captainName = $captain->getFullTitle();

            $this->line("  ✓ {$captainName} patrols {$sourceName} → {$destName} (Tier {$pirate->difficulty_tier})");
        }

        $this->info('Successfully assigned '.count($this->newPirates).' pirate encounters.');
    }

    private function showSummary(): void
    {
        $this->info('════════════════════════════════════════════════════════════════');
        $this->info('  EXPANSION COMPLETE');
        $this->info('════════════════════════════════════════════════════════════════');
        $this->newLine();

        $this->line("Galaxy: <fg=cyan>{$this->galaxy->name}</>");
        $this->newLine();

        $this->line('✓ Star Systems Added: <fg=green>'.count($this->newStars).'</>');
        $this->line('✓ Warp Gates Created: <fg=green>'.count($this->newGates).'</>');
        $this->line('✓ Trading Hubs Added: <fg=green>'.count($this->newHubs).'</>');
        $this->line('✓ Pirate Encounters: <fg=green>'.count($this->newPirates).'</>');
        $this->newLine();

        $totalStars = $this->galaxy->pointsOfInterest()->where('type', PointOfInterestType::STAR)->count();
        $totalGates = $this->galaxy->warpGates()->count();

        $this->line("Total Stars in Galaxy: <fg=yellow>{$totalStars}</>");
        $this->line("Total Warp Gates: <fg=yellow>{$totalGates}</>");
        $this->newLine();

        $this->info('Use `php artisan galaxy:view "'.$this->galaxy->name.'"` to view the expanded galaxy.');
    }

    // Helper methods for name generation

    private function generateStarName(): string
    {
        $prefixes = ['Alpha', 'Beta', 'Gamma', 'Delta', 'Epsilon', 'Zeta', 'Theta', 'Omega'];
        $suffixes = ['Prime', 'Major', 'Minor', 'Proxima', 'Centauri', 'Australis', 'Borealis'];
        $names = ['Rigel', 'Vega', 'Altair', 'Deneb', 'Arcturus', 'Sirius', 'Polaris', 'Betelgeuse'];

        $type = rand(1, 3);

        if ($type === 1) {
            return $prefixes[array_rand($prefixes)].' '.$names[array_rand($names)];
        } elseif ($type === 2) {
            return $names[array_rand($names)].' '.$suffixes[array_rand($suffixes)];
        } else {
            return $names[array_rand($names)].' '.rand(100, 999);
        }
    }

    private function generatePlanetName(): string
    {
        $adjectives = ['New', 'Nova', 'Neo', 'Prime', 'Greater', 'Lesser'];
        $names = ['Terra', 'Eden', 'Haven', 'Sanctuary', 'Frontier', 'Horizon', 'Dawn', 'Dusk'];

        if (rand(1, 2) === 1) {
            return $adjectives[array_rand($adjectives)].' '.$names[array_rand($names)];
        } else {
            return $names[array_rand($names)].' '.chr(rand(65, 90)); // A-Z
        }
    }

    private function generateHubName(): string
    {
        $types = ['Outpost', 'Station', 'Port', 'Haven', 'Exchange', 'Depot'];
        $adjectives = ['Frontier', 'Pioneer', 'Waystation', 'Crossroads', 'Gateway'];

        return $adjectives[array_rand($adjectives)].' '.$types[array_rand($types)];
    }

    private function randomStarClass(): string
    {
        $classes = ['O', 'B', 'A', 'F', 'G', 'K', 'M'];

        return $classes[array_rand($classes)];
    }

    private function getStarTemperature(string $stellarClass): int
    {
        // Realistic temperature ranges for stellar classifications (in Kelvin)
        return match ($stellarClass) {
            'O' => rand(30000, 50000),  // Blue supergiants
            'B' => rand(10000, 30000),  // Blue-white giants
            'A' => rand(7500, 10000),   // White stars
            'F' => rand(6000, 7500),    // Yellow-white stars
            'G' => rand(5200, 6000),    // Yellow stars (like our Sun)
            'K' => rand(3700, 5200),    // Orange dwarfs
            'M' => rand(2400, 3700),    // Red dwarfs
            default => rand(3000, 7000),
        };
    }

    private function randomPlanetType(): string
    {
        $types = ['terrestrial', 'gas_giant', 'ice', 'desert', 'oceanic', 'volcanic'];

        return $types[array_rand($types)];
    }

    private function randomAtmosphere(): string
    {
        $atmospheres = ['none', 'thin', 'breathable', 'toxic', 'dense', 'corrosive'];

        return $atmospheres[array_rand($atmospheres)];
    }

    private function findSectorForCoordinates(float $x, float $y): ?int
    {
        if (empty($this->sectors)) {
            return null;
        }

        foreach ($this->sectors as $sector) {
            if ($sector->containsCoordinates($x, $y)) {
                return $sector->id;
            }
        }

        return null;
    }
}
