<?php

namespace App\Console\Commands;

use App\Models\Mineral;
use App\Models\Plan;
use App\Models\Ship;
use App\Models\TradingHub;
use App\Models\TradingHubInventory;
use App\Models\TradingHubShip;
use App\Services\TradingService;
use DB;
use Illuminate\Console\Command;

class TradingHubPopulateInventory extends Command
{
    protected $signature = 'trading-hub:populate-inventory
                            {galaxy : Galaxy ID}
                            {--regenerate : Delete existing inventory and regenerate}';

    protected $description = 'Populate trading hubs with mineral inventory and upgrade plans';

    public function handle(): int
    {
        $galaxy_id = $this->argument('galaxy');
        $this->info('════════════════════════════════════════════════════════════════');
        $this->info('  TRADING HUB INVENTORY POPULATION');
        $this->info('════════════════════════════════════════════════════════════════');
        $this->newLine();

        // Get all minerals, plans, and ships
        $minerals = Mineral::all();
        $plans = Plan::all();
        $ships = Ship::where('is_available', true)->get();

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
        if (! $galaxy_id) {
            return Command::FAILURE;
        }
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
        $this->populateShipInventory($galaxy_id, $hubs, $ships);

        $this->newLine();
        $this->info('✅ Trading hub inventory population complete!');

        return Command::SUCCESS;
    }

    private function populateMineralInventory($hubs, $minerals): void
    {
        $this->info('Populating mineral inventory...');
        $progressBar = $this->output->createProgressBar($hubs->count());
        $progressBar->start();

        $tradingService = app(TradingService::class);
        $totalPopulated = 0;

        foreach ($hubs as $hub) {
            if ($tradingService->ensureInventoryPopulated($hub)) {
                $totalPopulated++;
            }
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();
        $this->info("Populated {$totalPopulated} hubs with mineral inventory");
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

            if (! $hasPlans) {
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

    private function populateShipInventory(int $galaxy_id, $hubs, $ships): void
    {
        $this->newLine();
        $this->info('Populating ship inventory...');
        $progressBar = $this->output->createProgressBar($hubs->count());
        $progressBar->start();

        $totalShips = 0;

        foreach ($hubs as $hub) {
            // Hub has shipyard if services array declares it, or based on tier probability
            $hubServices = $hub->services ?? [];
            $servicesIncludeShipyard = in_array('shipyard', $hubServices)
                || in_array('ship_sales', $hubServices);
            $hasShipyard = $servicesIncludeShipyard || match ($hub->getTier()) {
                'premium' => true,
                'major' => rand(1, 100) <= 70,
                'standard' => rand(1, 100) <= 30,
                default => false,
            };

            if (! $hasShipyard) {
                $progressBar->advance();

                continue;
            }

            // Each shipyard gets 2-6 ship types based on tier
            $shipCount = match ($hub->getTier()) {
                'premium' => rand(min(5, $ships->count()), $ships->count()),
                'major' => rand(3, min(6, $ships->count())),
                'standard' => rand(2, min(4, $ships->count())),
                default => min(2, $ships->count()),
            };

            $availableShips = $ships->random(min($shipCount, $ships->count()));

            foreach ($availableShips as $ship) {
                // Stock quantity based on ship rarity
                $quantity = match ($ship->rarity) {
                    'common' => rand(3, 8),
                    'uncommon' => rand(2, 5),
                    'rare' => rand(1, 3),
                    'very_rare' => rand(1, 2),
                    'legendary' => 1,
                    default => rand(2, 5),
                };

                // Random supply and demand levels (affects pricing)
                $demandLevel = rand(30, 70);
                $supplyLevel = rand(30, 70);

                // Calculate pricing
                $basePrice = $ship->base_price;
                $demandMultiplier = 1 + (($demandLevel - 50) / 100);
                $supplyMultiplier = 1 - (($supplyLevel - 50) / 100);
                $currentPrice = $basePrice * $demandMultiplier * $supplyMultiplier;

                // Create ship inventory record
                TradingHubShip::create([
                    'trading_hub_id' => $hub->id,
                    'galaxy_id' => $galaxy_id,
                    'ship_id' => $ship->id,
                    'quantity' => $quantity,
                    'current_price' => $currentPrice,
                    'demand_level' => $demandLevel,
                    'supply_level' => $supplyLevel,
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
