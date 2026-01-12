<?php

namespace Database\Seeders;

use App\Models\Galaxy;
use App\Models\PirateCaptain;
use App\Models\WarpLanePirate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class WarpLanePirateSeeder extends Seeder
{
    public function run(): void
    {
        $galaxies = Galaxy::all();
        $captains = PirateCaptain::all();

        if ($captains->isEmpty()) {
            $this->command->error('No pirate captains found. Run PirateCaptainSeeder first.');

            return;
        }

        $totalPiratePresence = 0;

        foreach ($galaxies as $galaxy) {
            // Get all active warp gates for this galaxy
            $gates = $galaxy->warpGates()
                ->where('is_hidden', false)
                ->where('status', 'active')
                ->get();

            if ($gates->isEmpty()) {
                continue;
            }

            // Calculate 10% of warp gates (minimum 1)
            $pirateGateCount = max(1, (int) round($gates->count() * 0.10));

            // Randomly select gates for pirate presence
            $selectedGates = $gates->random(min($pirateGateCount, $gates->count()));

            foreach ($selectedGates as $gate) {
                // Randomly select a captain
                $captain = $captains->random();

                WarpLanePirate::create([
                    'uuid' => Str::uuid(),
                    'warp_gate_id' => $gate->id,
                    'captain_id' => $captain->id,
                    'fleet_size' => rand(1, 4),
                    'difficulty_tier' => rand(1, 5),
                    'is_active' => true,
                    'last_encounter_at' => null,
                ]);

                $totalPiratePresence++;
            }

            $this->command->info("Galaxy '{$galaxy->name}': {$selectedGates->count()} pirate-controlled lanes out of {$gates->count()} total gates");
        }

        $this->command->info("Total pirate presence established: {$totalPiratePresence} warp lanes");
    }
}
