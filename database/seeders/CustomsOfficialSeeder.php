<?php

namespace Database\Seeders;

use App\Models\CustomsOfficial;
use App\Models\PointOfInterest;
use Illuminate\Database\Seeder;

class CustomsOfficialSeeder extends Seeder
{
    /**
     * Seed customs officials for inhabited systems
     *
     * One official per inhabited POI
     */
    public function run(): void
    {
        $this->command->info('Seeding customs officials...');

        // Get all inhabited POIs that don't have customs yet
        $inhabitedPois = PointOfInterest::where('is_inhabited', true)
            ->whereDoesntHave('customsOfficial')
            ->get();

        $created = 0;
        foreach ($inhabitedPois as $poi) {
            // Vary the type of official based on location
            if (random_int(0, 10) < 2) {
                // 20% corrupt officials
                CustomsOfficial::factory()
                    ->corrupt()
                    ->state(['poi_id' => $poi->id])
                    ->create();
            } else {
                // 80% honest officials
                CustomsOfficial::factory()
                    ->honest()
                    ->state(['poi_id' => $poi->id])
                    ->create();
            }
            $created++;
        }

        $this->command->info("Created {$created} customs officials");
    }
}
