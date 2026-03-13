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
                // Skip if vendor already exists at this POI in this galaxy
                if (GalaxyVendorProfile::where('galaxy_id', $galaxy->id)
                    ->where('poi_id', $poi->id)
                    ->exists()) {
                    continue;
                }

                try {
                    // Pick random vendor profile from global pool
                    $vendorProfile = $vendorProfiles->random();

                    // Pick a trading post (for default dialogue fallback)
                    $tradingPost = TradingPost::where('service_type', 'trading_hub')
                        ->inRandomOrder()
                        ->first();

                    // Criminality with variation
                    $criminality = max(0, min(1, $vendorProfile->criminality + random_int(-5, 5) / 100));

                    GalaxyVendorProfile::create([
                        'uuid' => \Illuminate\Support\Str::uuid(),
                        'galaxy_id' => $galaxy->id,
                        'poi_id' => $poi->id,
                        'vendor_profile_id' => $vendorProfile->id,
                        'trading_post_id' => $tradingPost?->id,
                        'service_type' => $vendorProfile->service_type,
                        'criminality' => $criminality,
                        'dialogue_generation_status' => 'pending',
                        'dialogue_generation_version' => 1,
                        'dialogue_generated_at' => null,
                    ]);

                    $totalCreated++;
                } catch (\Exception $e) {
                    $this->command->error("    Error creating vendor for {$poi->name}: {$e->getMessage()}");
                }
            }
        }

        $this->command->info("✓ Total vendor profile instances created: {$totalCreated}");
    }
}
