<?php

namespace Database\Seeders;

use App\Models\Galaxy;
use App\Models\GalaxyVendorState;
use App\Models\VendorProfile;
use Illuminate\Database\Seeder;

/**
 * Seed vendor states for each galaxy
 *
 * Creates galaxy-specific vendor state records from the permanent vendor profile templates
 * This allows vendors to have different reputations, markup changes, and interaction counts
 * in each galaxy
 */
class GalaxyVendorStateSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Seeding galaxy vendor states...');

        $galaxies = Galaxy::all();
        $allVendors = VendorProfile::all();

        if ($allVendors->isEmpty()) {
            $this->command->warn('No vendor profiles found. Skipping vendor state seeding.');
            return;
        }

        $totalCreated = 0;

        foreach ($galaxies as $galaxy) {
            $this->command->info("Processing {$galaxy->name}...");

            foreach ($allVendors as $vendor) {
                // Skip if state already exists
                if (GalaxyVendorState::where('galaxy_id', $galaxy->id)
                    ->where('vendor_profile_id', $vendor->id)
                    ->exists()) {
                    continue;
                }

                try {
                    GalaxyVendorState::create([
                        'uuid' => \Illuminate\Support\Str::uuid(),
                        'galaxy_id' => $galaxy->id,
                        'vendor_profile_id' => $vendor->id,
                        'markup_modifier' => 0,          // Starts at template baseline
                        'interaction_count' => 0,
                        'average_satisfaction' => null,
                        'price_multiplier_base' => 100,  // 100% = baseline
                    ]);

                    $totalCreated++;
                } catch (\Exception $e) {
                    $this->command->error("    Error creating state for {$vendor->name}: {$e->getMessage()}");
                }
            }

            $this->command->info("  Created {$allVendors->count()} vendor states");
        }

        $this->command->info("✓ Total vendor states created: {$totalCreated}");
    }
}
