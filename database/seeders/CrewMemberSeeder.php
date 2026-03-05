<?php

namespace Database\Seeders;

use App\Models\CrewMember;
use App\Models\Galaxy;
use Illuminate\Database\Seeder;

class CrewMemberSeeder extends Seeder
{
    /**
     * Seed crew members for galaxies
     *
     * Creates a pool of 50-80 crew members per galaxy, distributed across POIs
     */
    public function run(): void
    {
        $this->command->info('Seeding crew members...');

        $galaxies = Galaxy::all();
        $totalCreated = 0;

        foreach ($galaxies as $galaxy) {
            // Generate 50-80 crew members per galaxy
            $crewCount = random_int(50, 80);

            // Get POIs in this galaxy for assignment
            $pois = $galaxy->pointsOfInterest()->get();

            if ($pois->isEmpty()) {
                $this->command->warn("Galaxy {$galaxy->name} has no POIs, skipping crew seeding");
                continue;
            }

            try {
                CrewMember::factory($crewCount)
                    ->state(function () use ($galaxy, $pois) {
                        return [
                            'galaxy_id' => $galaxy->id,
                            'current_poi_id' => $pois->random()->id,
                        ];
                    })
                    ->create();

                $totalCreated += $crewCount;
                $this->command->info("Created {$crewCount} crew members for {$galaxy->name}");
            } catch (\Exception $e) {
                $this->command->error("Error creating crew for {$galaxy->name}: {$e->getMessage()}");
            }
        }

        $this->command->info("Total crew members created: {$totalCreated}");
    }
}
