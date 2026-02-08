<?php

namespace Database\Seeders;

use App\Models\Galaxy;
use App\Models\PointOfInterest;
use App\Models\PrecursorShip;
use App\Services\PrecursorRumorService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PrecursorShipSeeder extends Seeder
{
    /**
     * Seed one Precursor Ship per galaxy in INTERSTELLAR VOID
     *
     * Requirements:
     * - Must be far from any star/POI (minimum 20 units away)
     * - Placed in "dead space" between star systems
     * - Random coordinates within galaxy bounds
     */
    public function run(): void
    {
        $galaxies = Galaxy::all();

        foreach ($galaxies as $galaxy) {
            $this->seedPrecursorShip($galaxy);
        }

        $this->command->info('✨ Precursor Ships hidden in the void...');
    }

    public function seedPrecursorShip(Galaxy $galaxy): void
    {
        // Get all POIs in this galaxy
        $pois = PointOfInterest::where('galaxy_id', $galaxy->id)
            ->select('x', 'y')
            ->get();

        $maxAttempts = 100;
        $attempts = 0;
        $minDistanceFromAnyPOI = 20; // Must be at least 20 units from any star

        do {
            // Generate random coordinates in interstellar space
            // Use galaxy dimensions with 10% margin from edges
            $minX = (int) ($galaxy->width * 0.1);
            $maxX = (int) ($galaxy->width * 0.9);
            $minY = (int) ($galaxy->height * 0.1);
            $maxY = (int) ($galaxy->height * 0.9);

            $x = rand($minX, $maxX);
            $y = rand($minY, $maxY);

            // Check distance from all POIs
            $tooClose = false;
            foreach ($pois as $poi) {
                $distance = sqrt(
                    pow($x - $poi->x, 2) + pow($y - $poi->y, 2)
                );

                if ($distance < $minDistanceFromAnyPOI) {
                    $tooClose = true;
                    break;
                }
            }

            $attempts++;

            if (! $tooClose) {
                break; // Found a good spot
            }

        } while ($attempts < $maxAttempts);

        if ($attempts >= $maxAttempts) {
            // Fallback: Place in center of galaxy
            $x = (int) ($galaxy->width / 2);
            $y = (int) ($galaxy->height / 2);
            if ($this->command) {
                $this->command->warn("Could not find isolated spot, placing Precursor Ship at center ({$x}, {$y})");
            }
        }

        // Create the Precursor Ship
        $precursorShip = PrecursorShip::create([
            'uuid' => Str::uuid(),
            'galaxy_id' => $galaxy->id,
            'x' => $x,
            'y' => $y,
            'is_discovered' => false,
            'hull' => 1000000,
            'max_hull' => 1000000,
            'weapons' => 10000,
            'sensors' => 100,
            'speed' => 10000,
            'warp_drive' => 100,
            'cargo_capacity' => 1000000,
            'current_cargo' => 0,
            'fuel' => 999999999,
            'max_fuel' => 999999999,
            'precursor_name' => $this->generatePrecursorName(),
        ]);

        if ($this->command) {
            $this->command->info("🛸 Precursor Ship hidden at ({$x}, {$y}) in galaxy '{$galaxy->name}'");
        }

        // Generate rumors at all ship yards (they all think they know where it is... they're all wrong)
        $rumorService = app(PrecursorRumorService::class);
        $rumorCount = $rumorService->generateRumorsForGalaxy($galaxy);

        if ($this->command && $rumorCount > 0) {
            $this->command->info("   └─ Generated {$rumorCount} (wrong) rumors at ship yards");
        }
    }

    /**
     * Set the command instance for output
     */
    public function setCommand($command): void
    {
        $this->command = $command;
    }

    /**
     * Generate a thematic Precursor ship name
     */
    private function generatePrecursorName(): string
    {
        $prefixes = [
            'Void', 'Star', 'Quantum', 'Stellar', 'Cosmic',
            'Temporal', 'Eternal', 'Ancient', 'Celestial', 'Infinity',
        ];

        $suffixes = [
            'Strider', 'Walker', 'Wanderer', 'Seeker', 'Herald',
            'Architect', 'Engineer', 'Sentinel', 'Guardian', 'Harbinger',
        ];

        return $prefixes[array_rand($prefixes)].' '.$suffixes[array_rand($suffixes)];
    }
}
