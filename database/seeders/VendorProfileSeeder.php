<?php

namespace Database\Seeders;

use App\Models\Galaxy;
use App\Models\PointOfInterest;
use App\Models\TradingPost;
use App\Models\VendorProfile;
use Illuminate\Database\Seeder;

/**
 * Seed vendor instances for each POI in each galaxy
 *
 * Creates vendor instances from trading post templates, one per POI per service type.
 * Service types: trading_hub, salvage_yard, shipyard, market
 */
class VendorProfileSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Seeding vendor instances...');

        $galaxies = Galaxy::all();
        $totalCreated = 0;

        foreach ($galaxies as $galaxy) {
            $this->command->info("Processing {$galaxy->name}...");

            // Find POIs that have trading hubs
            $tradingHubPois = PointOfInterest::where('galaxy_id', $galaxy->id)
                ->whereHas('tradingHub')
                ->get();

            $this->command->info("  Found {$tradingHubPois->count()} trading hubs");

            // Get random trading posts for trading hub service type
            $tradingPosts = TradingPost::where('service_type', 'trading_hub')->get();

            if ($tradingPosts->isEmpty()) {
                $this->command->warn("  No trading_hub trading post templates found. Skipping.");
                continue;
            }

            foreach ($tradingHubPois as $poi) {
                // Skip if vendor for this service_type already exists at this POI
                if (VendorProfile::where('poi_id', $poi->id)->where('service_type', 'trading_hub')->exists()) {
                    continue;
                }

                try {
                    // Pick random trading post for this vendor
                    $tradingPost = $tradingPosts->random();

                    // Create vendor instance
                    // Clamp criminality to valid range [0.0, 1.0]
                    $criminality = max(0, min(1, $tradingPost->base_criminality + random_int(-5, 5) / 100));

                    VendorProfile::create([
                        'uuid' => \Illuminate\Support\Str::uuid(),
                        'galaxy_id' => $galaxy->id,
                        'poi_id' => $poi->id,
                        'trading_post_id' => $tradingPost->id,
                        'service_type' => 'trading_hub',
                        'criminality' => $criminality,
                        'personality' => $tradingPost->personality,
                        'dialogue_pool' => $tradingPost->dialogue_pool,
                        'markup_base' => $tradingPost->markup_base,
                    ]);

                    $totalCreated++;
                } catch (\Exception $e) {
                    $this->command->error("    Error creating vendor for {$poi->name}: {$e->getMessage()}");
                }
            }
        }

        $this->command->info("✓ Total vendor instances created: {$totalCreated}");
    }
}
