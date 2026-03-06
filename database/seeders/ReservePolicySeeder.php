<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ReservePolicySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Creates default reserve policies for all galaxies.
     * Policies define minimum inventory levels and NPC fallback pricing.
     */
    public function run(): void
    {
        $galaxies = \App\Models\Galaxy::all();
        $commodities = \App\Models\Commodity::all();

        foreach ($galaxies as $galaxy) {
            // System-wide default policy (commodity_id = null)
            // Applied to all commodities without explicit policy
            \App\Models\ReservePolicy::firstOrCreate(
                [
                    'galaxy_id' => $galaxy->id,
                    'commodity_id' => null,
                ],
                [
                    'min_qty_on_hand' => 5000,           // Default safety stock
                    'npc_fallback_enabled' => true,
                    'npc_price_multiplier' => 1.5,       // 50% markup on NPC supply
                    'description' => 'Default system-wide reserve policy',
                ]
            );

            // Per-commodity policies for rarer items (higher minimums)
            foreach ($commodities as $commodity) {
                // Increase minimum inventory for rarer items
                $minQty = match ($commodity->rarity->value) {
                    'abundant' => 8000,
                    'common' => 6000,
                    'uncommon' => 5000,
                    'rare' => 3000,
                    'epic' => 2000,
                    'very_rare' => 1000,
                    'legendary' => 500,
                    'mythic' => 200,
                    default => 5000,
                };

                // Adjust NPC multiplier: rarer items have higher markup
                $multiplier = match ($commodity->rarity->value) {
                    'abundant' => 1.25,
                    'common' => 1.35,
                    'uncommon' => 1.50,
                    'rare' => 1.75,
                    'epic' => 2.00,
                    'very_rare' => 2.50,
                    'legendary' => 3.00,
                    'mythic' => 5.00,
                    default => 1.5,
                };

                \App\Models\ReservePolicy::firstOrCreate(
                    [
                        'galaxy_id' => $galaxy->id,
                        'commodity_id' => $commodity->id,
                    ],
                    [
                        'min_qty_on_hand' => $minQty,
                        'npc_fallback_enabled' => true,
                        'npc_price_multiplier' => $multiplier,
                        'description' => "Reserve policy for {$commodity->name} ({$commodity->rarity->value})",
                    ]
                );
            }
        }

        $policyCount = \App\Models\ReservePolicy::count();
        $this->command->info("Reserve policies seeded: {$policyCount} total policies across all galaxies");
    }
}
