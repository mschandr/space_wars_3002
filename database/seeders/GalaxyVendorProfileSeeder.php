<?php

namespace Database\Seeders;

use App\Models\Galaxy;
use App\Models\GalaxyVendorProfile;
use App\Models\PointOfInterest;
use App\Models\TradingPost;
use App\Models\VendorProfile;
use Illuminate\Database\Seeder;

/**
 * Seed galaxy-specific vendor profile instances
 *
 * Creates per-galaxy+POI instances of vendor profiles from the global pool.
 * Mirrors the pattern of GalaxyVendorStateSeeder.
 */
class GalaxyVendorProfileSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Seeding galaxy vendor profile instances...');

        $galaxies = Galaxy::all();
        $totalCreated = 0;

        foreach ($galaxies as $galaxy) {
            $this->command->info("Processing {$galaxy->name}...");

            // Find POIs that have trading hubs
            $tradingHubPois = PointOfInterest::where('galaxy_id', $galaxy->id)
                ->whereHas('tradingHub')
                ->get();

            $this->command->info("  Found {$tradingHubPois->count()} trading hubs");

            // Get all vendor profiles (global templates)
            $vendorProfiles = VendorProfile::all();

            if ($vendorProfiles->isEmpty()) {
                $this->command->warn('  No vendor profile templates found. Skipping.');
                continue;
            }

            foreach ($tradingHubPois as $poi) {
                $hub = $poi->tradingHub;

                // Determine which shop types are present at this hub
                $serviceTypes = ['trading_hub'];
                if ($hub->has_salvage_yard) {
                    $serviceTypes[] = 'salvage_yard';
                }
                if ($hub->hasShipyard()) {
                    $serviceTypes[] = 'shipyard';
                }
                if (rand(1, 100) <= 80) {
                    $serviceTypes[] = 'repair_yard';
                }
                if (rand(1, 100) <= 70) {
                    $serviceTypes[] = 'bartender';
                }
                if (rand(1, 100) <= 30) {
                    $serviceTypes[] = 'information_broker';
                }

                foreach ($serviceTypes as $serviceType) {
                    // Skip if vendor already exists for this shop type at this POI
                    if (GalaxyVendorProfile::where('galaxy_id', $galaxy->id)
                        ->where('poi_id', $poi->id)
                        ->where('service_type', $serviceType)
                        ->exists()) {
                        continue;
                    }

                    try {
                        // Pick a vendor profile matching the shop type from the global pool
                        $vendorProfile = $vendorProfiles->where('service_type', $serviceType)->random()
                            ?? $vendorProfiles->random();

                        $tradingPost = TradingPost::where('service_type', $serviceType)
                            ->inRandomOrder()
                            ->first();

                        $criminality = max(0, min(1, $vendorProfile->criminality + random_int(-5, 5) / 100));

                        GalaxyVendorProfile::create([
                            'uuid' => \Illuminate\Support\Str::uuid(),
                            'galaxy_id' => $galaxy->id,
                            'poi_id' => $poi->id,
                            'vendor_profile_id' => $vendorProfile->id,
                            'trading_post_id' => $tradingPost?->id,
                            'service_type' => $serviceType,
                            'criminality' => $criminality,
                            'dialogue_generation_status' => 'pending',
                            'dialogue_generation_version' => 1,
                            'dialogue_generated_at' => null,
                        ]);

                        $totalCreated++;
                    } catch (\Exception $e) {
                        $this->command->error("    Error creating {$serviceType} vendor for {$poi->name}: {$e->getMessage()}");
                    }
                }
            }
        }

        $this->command->info("✓ Total vendor profile instances created: {$totalCreated}");
    }
}
