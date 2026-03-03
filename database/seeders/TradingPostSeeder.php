<?php

namespace Database\Seeders;

use App\Models\TradingPost;
use Illuminate\Database\Seeder;

/**
 * Seed predefined trading post templates
 *
 * These are global templates. Vendor instances are created from these templates
 * for each POI in each galaxy that offers that service.
 */
class TradingPostSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Seeding trading post templates...');

        // Create multiple templates per service type
        $templates = [
            // Trading hubs (12 templates)
            ['name' => 'Kovac\'s Emporium', 'service_type' => 'trading_hub', 'criminality' => 0.15],
            ['name' => 'Chen\'s Exchange', 'service_type' => 'trading_hub', 'criminality' => 0.10],
            ['name' => 'The Wandering Merchant', 'service_type' => 'trading_hub', 'criminality' => 0.20],
            ['name' => 'Port Authority', 'service_type' => 'trading_hub', 'criminality' => 0.05],
            ['name' => 'Starlight Bazaar', 'service_type' => 'trading_hub', 'criminality' => 0.18],
            ['name' => 'Void Commerce', 'service_type' => 'trading_hub', 'criminality' => 0.22],
            ['name' => 'The Gilded Gate', 'service_type' => 'trading_hub', 'criminality' => 0.12],
            ['name' => 'Neutral Ground', 'service_type' => 'trading_hub', 'criminality' => 0.25],
            ['name' => 'The Honest Scale', 'service_type' => 'trading_hub', 'criminality' => 0.02],
            ['name' => 'Twilight Trading', 'service_type' => 'trading_hub', 'criminality' => 0.30],
            ['name' => 'Fortune\'s Wheel', 'service_type' => 'trading_hub', 'criminality' => 0.28],
            ['name' => 'Black Nova Trading', 'service_type' => 'trading_hub', 'criminality' => 0.35],

            // Salvage yards (8 templates, more criminal)
            ['name' => 'The Rusty Bolt', 'service_type' => 'salvage_yard', 'criminality' => 0.45],
            ['name' => 'Salvage Prime', 'service_type' => 'salvage_yard', 'criminality' => 0.35],
            ['name' => 'The Wreckage Dealer', 'service_type' => 'salvage_yard', 'criminality' => 0.55],
            ['name' => 'Scrap & Scavenge', 'service_type' => 'salvage_yard', 'criminality' => 0.40],
            ['name' => 'Junk Paradise', 'service_type' => 'salvage_yard', 'criminality' => 0.50],
            ['name' => 'The Bone Yard', 'service_type' => 'salvage_yard', 'criminality' => 0.60],
            ['name' => 'Iron Recovery', 'service_type' => 'salvage_yard', 'criminality' => 0.38],
            ['name' => 'The Salvage Master', 'service_type' => 'salvage_yard', 'criminality' => 0.42],

            // Shipyards (8 templates, legitimate)
            ['name' => 'Titan Yards', 'service_type' => 'shipyard', 'criminality' => 0.05],
            ['name' => 'Nova Shipworks', 'service_type' => 'shipyard', 'criminality' => 0.08],
            ['name' => 'Stellar Construction', 'service_type' => 'shipyard', 'criminality' => 0.03],
            ['name' => 'The Foundry', 'service_type' => 'shipyard', 'criminality' => 0.06],
            ['name' => 'Apex Drydock', 'service_type' => 'shipyard', 'criminality' => 0.04],
            ['name' => 'Void Engineering', 'service_type' => 'shipyard', 'criminality' => 0.10],
            ['name' => 'The Assembly', 'service_type' => 'shipyard', 'criminality' => 0.02],
            ['name' => 'Pioneer Shipyard', 'service_type' => 'shipyard', 'criminality' => 0.07],

            // Markets (8 templates, mixed)
            ['name' => 'Central Market', 'service_type' => 'market', 'criminality' => 0.15],
            ['name' => 'The Bazaar', 'service_type' => 'market', 'criminality' => 0.25],
            ['name' => 'Trade Floor', 'service_type' => 'market', 'criminality' => 0.12],
            ['name' => 'Commerce Commons', 'service_type' => 'market', 'criminality' => 0.10],
            ['name' => 'The Exchange Hub', 'service_type' => 'market', 'criminality' => 0.20],
            ['name' => 'Market Square', 'service_type' => 'market', 'criminality' => 0.18],
            ['name' => 'Trading Post', 'service_type' => 'market', 'criminality' => 0.22],
            ['name' => 'The Fair', 'service_type' => 'market', 'criminality' => 0.28],
        ];

        foreach ($templates as $template) {
            if (TradingPost::where('name', $template['name'])->exists()) {
                continue;
            }

            TradingPost::factory()
                ->state([
                    'name' => $template['name'],
                    'service_type' => $template['service_type'],
                    'base_criminality' => $template['criminality'],
                ])
                ->create();
        }

        $totalCount = TradingPost::count();
        $this->command->info("Trading post templates seeded: {$totalCount} total");
    }
}
