<?php

namespace Database\Seeders;

use App\Models\CustomsOfficial;
use App\Models\Galaxy;
use App\Models\GalaxyCustomsRecord;
use Illuminate\Database\Seeder;

/**
 * Seed customs records for each galaxy
 *
 * Creates galaxy-specific customs record entries from the permanent customs official templates
 * This allows officers to build corruption, reputation, and history with players per galaxy
 */
class GalaxyCustomsRecordSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Seeding galaxy customs records...');

        $galaxies = Galaxy::all();
        $allOfficials = CustomsOfficial::all();

        if ($allOfficials->isEmpty()) {
            $this->command->warn('No customs officials found. Skipping customs record seeding.');
            return;
        }

        $totalCreated = 0;

        foreach ($galaxies as $galaxy) {
            $this->command->info("Processing {$galaxy->name}...");

            foreach ($allOfficials as $official) {
                // Skip if record already exists
                if (GalaxyCustomsRecord::where('galaxy_id', $galaxy->id)
                    ->where('customs_official_id', $official->id)
                    ->exists()) {
                    continue;
                }

                try {
                    GalaxyCustomsRecord::create([
                        'uuid' => \Illuminate\Support\Str::uuid(),
                        'galaxy_id' => $galaxy->id,
                        'customs_official_id' => $official->id,
                        'total_checks' => 0,
                        'times_fined' => 0,
                        'times_bribed' => 0,
                        'total_bribes_paid' => 0,
                        'actual_honesty' => null,      // Starts at official's base honesty
                        'relationship_score' => 0,
                    ]);

                    $totalCreated++;
                } catch (\Exception $e) {
                    $this->command->error("    Error creating record for {$official->name}: {$e->getMessage()}");
                }
            }

            $this->command->info("  Created {$allOfficials->count()} customs records");
        }

        $this->command->info("✓ Total customs records created: {$totalCreated}");
    }
}
