<?php

namespace App\Console\Commands;

use App\Models\Mineral;
use App\Models\Plan;
use App\Models\Ship;
use App\Models\TradingHub;
use App\Models\TradingHubInventory;
use App\Models\TradingHubShip;
use DB;
use Illuminate\Console\Command;

class TradingHubPopulateInventory extends Command
{
    protected $signature = 'trading-hub:populate-inventory
                            {--regenerate : Delete existing inventory and regenerate}';

    protected $description = 'Populate trading hubs with mineral inventory and upgrade plans';

    public function handle(): int
    {
        $this->info('════════════════════════════════════════════════════════════════');
        $this->info('  TRADING HUB INVENTORY POPULATION');
        $this->info('════════════════════════════════════════════════════════════════');
        $this->newLine();

        // Get all minerals, plans, and ships
        $minerals = Mineral::all();
        $plans    = Plan::all();
        $ships    = Ship::where('is_available', true)->get();

        if ($minerals->isEmpty()) {
            $this->error('No minerals found. Run MineralSeeder first.');
            return Command::FAILURE;
        }

        if ($plans->isEmpty()) {
            $this->warn('No upgrade plans found. Run PlansSeeder first.');
        }

        if ($ships->isEmpty()) {
            $this->error('No ships found. Run ShipTypesSeeder first.');
            return Command::FAILURE;
        }

        // Get all trading hubs
        $hubs = TradingHub::where('is_active', true)->get();

        if ($hubs->isEmpty()) {
            $this->error('No active trading hubs found.');
            return Command::FAILURE;
        }

        $this->info("Found {$hubs->count()} trading hubs");
        $this->info("Found {$minerals->count()} minerals");
        $this->info("Found {$plans->count()} upgrade plans");
        $this->info("Found {$ships->count()} ship types");
        $this->newLine();

        // Delete existing inventory if regenerating
        if ($this->option('regenerate')) {
            $this->info('Deleting existing inventory...');
            TradingHubInventory::truncate();
            DB::table('trading_hub_plans')->truncate();
            TradingHubShip::truncate();
        }

        // Populate mineral inventory
        $this->populateMineralInventory($hubs, $minerals);

        // Populate upgrade plans
        $this->populateUpgradePlans($hubs, $plans);

        // Populate ship inventory
        $this->populateShipInventory($hubs, $ships);

        $this->newLine();
        $this->info('✅ Trading hub inventory population complete!');

        return Command::SUCCESS;
    }

    private function populateMineralInventory($hubs, $minerals): void
    {
        $this->info('Populating mineral inventory...');
        $progressBar = $this->output->createProgressBar($hubs->count());
        $progressBar->start();

        $totalInventory = 0;

        foreach ($hubs as $hub) {
            // Each hub gets a random selection of minerals (60-100% of all minerals)
            $availableMinerals = $minerals->random(rand((int)($minerals->count() * 0.6), $minerals->count()));

            foreach ($availableMinerals as $mineral) {
                // Random stock levels based on mineral rarity
                $baseStock = match ($mineral->rarity) {
                    'common'    => rand(5000, 15000),
                    'uncommon'  => rand(2000, 8000),
                    'rare'      => rand(500, 3000),
                    'very_rare' => rand(100, 1000),
                    'legendary' => rand(10, 200),
                    default     => rand(1000, 5000),
                };

                // Random supply and demand levels (affects pricing)
                $demandLevel = rand(30, 70);
                $supplyLevel = rand(30, 70);

                // Calculate initial pricing
                $baseValue        = $mineral->base_value ?? 100;
                $demandMultiplier = 1 + (($demandLevel - 50) / 100);
                $supplyMultiplier = 1 - (($supplyLevel - 50) / 100);
                $currentPrice     = $baseValue * $demandMultiplier * $supplyMultiplier;

                // Hub buys at lower price, sells at higher price (15% spread)
                $spread    = 0.15;
                $buyPrice  = $currentPrice * (1 - $spread);
                $sellPrice = $currentPrice * (1 + $spread);

                // Create inventory record with prices
                TradingHubInventory::create([
                    'trading_hub_id'    => $hub->id,
                    'mineral_id'        => $mineral->id,
                    'quantity'          => $baseStock,
                    'current_price'     => $currentPrice,
                    'buy_price'         => $buyPrice,
                    'sell_price'        => $sellPrice,
                    'demand_level'      => $demandLevel,
                    'supply_level'      => $supplyLevel,
                    'last_price_update' => now(),
                ]);

                $totalInventory++;
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();
        $this->info("Created {$totalInventory} inventory records");
    }

    private function populateUpgradePlans($hubs, $plans): void
    {
        if ($plans->isEmpty()) {
            return;
        }

        $this->newLine();
        $this->info('Populating upgrade plans...');
        $progressBar = $this->output->createProgressBar($hubs->count());
        $progressBar->start();

        $totalPlans = 0;

        foreach ($hubs as $hub) {
            // Hubs with 'has_plans' flag get upgrade plans
            // Otherwise, 30% chance to have plans
            $hasPlans = $hub->has_plans || rand(1, 100) <= 30;

            if (!$hasPlans) {
                $progressBar->advance();
                continue;
            }

            // Each hub gets 3-8 random plans
            $hubPlans = $plans->random(rand(3, min(8, $plans->count())));

            foreach ($hubPlans as $plan) {
                $hub->plans()->attach($plan->id);
                $totalPlans++;
            }

            // Update hub flag
            $hub->has_plans = true;
            $hub->save();

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();
        $this->info("Assigned {$totalPlans} upgrade plans to hubs");
    }

    private function populateShipInventory($hubs, $ships): void
    {
        $this->newLine();
        $this->info('Populating ship inventory...');
        $progressBar = $this->output->createProgressBar($hubs->count());
        $progressBar->start();

        $totalShips = 0;

        foreach ($hubs as $hub) {
            // Determine if this hub has a shipyard based on tier
            // Premium hubs: 100% chance, Major hubs: 70% chance, Standard hubs: 30% chance
            $hasShipyard = match ($hub->getTier()) {
                'premium'  => true,
                'major'    => rand(1, 100) <= 70,
                'standard' => rand(1, 100) <= 30,
                default    => false,
            };

            if (!$hasShipyard) {
                $progressBar->advance();
                continue;
            }

            // Each shipyard gets 2-6 ship types based on tier
            $shipCount = match ($hub->getTier()) {
                'premium'  => rand(5, $ships->count()),
                'major'    => rand(3, 6),
                'standard' => rand(2, 4),
                default    => 2,
            };

            $availableShips = $ships->random(min($shipCount, $ships->count()));

            foreach ($availableShips as $ship) {
                // Stock quantity based on ship rarity
                $quantity = match ($ship->rarity) {
                    'common'    => rand(3, 8),
                    'uncommon'  => rand(2, 5),
                    'rare'      => rand(1, 3),
                    'very_rare' => rand(1, 2),
                    'legendary' => 1,
                    default     => rand(2, 5),
                };

                // Random supply and demand levels (affects pricing)
                $demandLevel = rand(30, 70);
                $supplyLevel = rand(30, 70);

                // Calculate pricing
                $basePrice        = $ship->base_price;
                $demandMultiplier = 1 + (($demandLevel - 50) / 100);
                $supplyMultiplier = 1 - (($supplyLevel - 50) / 100);
                $currentPrice     = $basePrice * $demandMultiplier * $supplyMultiplier;

                // Create ship inventory record
                TradingHubShip::create([
                    'trading_hub_id'    => $hub->id,
                    'ship_id'           => $ship->id,
                    'quantity'          => $quantity,
                    'current_price'     => $currentPrice,
                    'demand_level'      => $demandLevel,
                    'supply_level'      => $supplyLevel,
                    'last_price_update' => now(),
                ]);

                $totalShips++;
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();
        $this->info("Created {$totalShips} ship inventory records");
    }
}
