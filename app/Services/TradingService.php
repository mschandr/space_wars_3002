<?php

namespace App\Services;

use App\Models\Mineral;
use App\Models\Player;
use App\Models\PlayerCargo;
use App\Models\PlayerPriceSighting;
use App\Models\PlayerShip;
use App\Models\PlayerTradeTransaction;
use App\Models\TradingHub;
use App\Models\TradingHubInventory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Trading Service
 *
 * Handles mineral buying and selling transactions between players and trading hubs
 *
 * XP Awards:
 * - Buy: 1 XP per 10 units (min 5 XP)
 * - Sell: 1 XP per 100 credits revenue (min 10 XP)
 */
class TradingService
{
    /**
     * Buy minerals from a trading hub
     *
     * @return array ['success' => bool, 'message' => string, 'xp_earned' => int]
     */
    public function buyMineral(Player $player, PlayerShip $ship, TradingHubInventory $inventory, int $quantity): array
    {
        $mineral = $inventory->mineral;
        $isTutorial = ! $player->hasCompletedTutorial('first_mineral_buy');
        $totalCost = $isTutorial ? 0 : $inventory->sell_price * $quantity;

        // Validations
        if (! $inventory->hasStock($quantity)) {
            return [
                'success' => false,
                'message' => 'Insufficient stock available',
                'xp_earned' => 0,
            ];
        }

        if (! $isTutorial && $player->credits < $totalCost) {
            return [
                'success' => false,
                'message' => 'Insufficient credits',
                'xp_earned' => 0,
            ];
        }

        $availableSpace = $ship->getEffectiveCargoHold() - $ship->current_cargo;
        if ($availableSpace < $quantity) {
            return [
                'success' => false,
                'message' => 'Insufficient cargo space',
                'xp_earned' => 0,
            ];
        }

        // Execute transaction atomically
        $xpEarned = (int) max(5, $quantity / 10); // 1 XP per 10 units, min 5
        $unitPrice = $inventory->sell_price; // Capture before removeStock recalculates prices

        return DB::transaction(function () use ($player, $ship, $inventory, $mineral, $quantity, $totalCost, $xpEarned, $unitPrice, $isTutorial) {
            if (! $isTutorial) {
                $player->deductCredits($totalCost);
            }
            $inventory->removeStock($quantity);

            // Add to player cargo
            $playerCargo = PlayerCargo::firstOrNew([
                'player_ship_id' => $ship->id,
                'mineral_id' => $mineral->id,
            ]);
            $playerCargo->quantity = ($playerCargo->quantity ?? 0) + $quantity;
            $playerCargo->save();

            // Update ship cargo
            $ship->current_cargo += $quantity;
            $ship->save();

            // Award XP for trading
            $player->addExperience($xpEarned);

            // Log transaction
            PlayerTradeTransaction::create([
                'player_id' => $player->id,
                'trading_hub_id' => $inventory->trading_hub_id,
                'mineral_id' => $mineral->id,
                'transaction_type' => 'buy',
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'total_amount' => $totalCost,
                'credits_after' => $player->credits,
                'transacted_at' => now(),
            ]);

            if ($isTutorial) {
                $player->completeTutorial('first_mineral_buy');
            }

            return [
                'success' => true,
                'message' => "Successfully purchased {$quantity} units of {$mineral->name}",
                'total_cost' => $totalCost,
                'xp_earned' => $xpEarned,
                'tutorial' => $isTutorial,
            ];
        });
    }

    /**
     * Sell minerals to a trading hub
     *
     * @return array ['success' => bool, 'message' => string, 'xp_earned' => int]
     */
    public function sellMineral(Player $player, PlayerShip $ship, PlayerCargo $cargo, TradingHubInventory $hubInventory, int $quantity): array
    {
        $mineral = $cargo->mineral;
        $isTutorial = ! $player->hasCompletedTutorial('first_mineral_sell');

        // Validations
        if ($cargo->quantity < $quantity) {
            return [
                'success' => false,
                'message' => 'You don\'t have that many units',
                'xp_earned' => 0,
            ];
        }

        $unitPrice = $hubInventory->buy_price; // Capture before addStock recalculates prices
        $totalRevenue = $isTutorial ? 0 : $unitPrice * $quantity;

        // Execute transaction atomically
        $xpEarned = (int) max(10, $totalRevenue / 100); // 1 XP per 100 credits, min 10

        return DB::transaction(function () use ($player, $ship, $cargo, $hubInventory, $mineral, $quantity, $totalRevenue, $xpEarned, $unitPrice, $isTutorial) {
            if (! $isTutorial) {
                $player->addCredits($totalRevenue);
            }
            $hubInventory->addStock($quantity);

            // Remove from player cargo
            $cargo->quantity -= $quantity;
            if ($cargo->quantity <= 0) {
                $cargo->delete();
            } else {
                $cargo->save();
            }

            // Update ship cargo
            $ship->current_cargo -= $quantity;
            $ship->save();

            // Award XP for trading
            $player->addExperience($xpEarned);

            // Log transaction
            PlayerTradeTransaction::create([
                'player_id' => $player->id,
                'trading_hub_id' => $hubInventory->trading_hub_id,
                'mineral_id' => $mineral->id,
                'transaction_type' => 'sell',
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'total_amount' => $totalRevenue,
                'credits_after' => $player->credits,
                'transacted_at' => now(),
            ]);

            if ($isTutorial) {
                $player->completeTutorial('first_mineral_sell');
            }

            return [
                'success' => true,
                'message' => "Successfully sold {$quantity} units of {$mineral->name}",
                'total_revenue' => $totalRevenue,
                'xp_earned' => $xpEarned,
                'tutorial' => $isTutorial,
            ];
        });
    }

    /**
     * Get maximum affordable quantity
     */
    public function getMaxAffordableQuantity(Player $player, TradingHubInventory $inventory): int
    {
        $maxByCredits = (int) floor($player->credits / $inventory->sell_price);
        $maxByStock = $inventory->quantity;

        return min($maxByCredits, $maxByStock);
    }

    /**
     * Get maximum sellable quantity (limited by cargo)
     */
    public function getMaxSellableQuantity(PlayerCargo $cargo): int
    {
        return $cargo->quantity;
    }

    /**
     * Ensure a trading hub has mineral inventory populated.
     *
     * Lazy generation: if a hub has zero inventory rows, populate it
     * with a random selection of minerals (same logic as the bulk command).
     *
     * @return bool True if population was performed, false if already populated
     */
    public function ensureInventoryPopulated(TradingHub $hub): bool
    {
        if ($hub->inventories()->exists()) {
            return false;
        }

        $minerals = Mineral::all();

        if ($minerals->isEmpty()) {
            return false;
        }

        // Each hub gets 60-100% of all minerals
        $count = rand((int) ($minerals->count() * 0.6), $minerals->count());
        $availableMinerals = $minerals->random($count);

        foreach ($availableMinerals as $mineral) {
            $baseStock = match ($mineral->rarity) {
                'common' => rand(5000, 15000),
                'uncommon' => rand(2000, 8000),
                'rare' => rand(500, 3000),
                'very_rare' => rand(100, 1000),
                'legendary' => rand(10, 200),
                default => rand(1000, 5000),
            };

            $tradingConfig = config('game_config.trading_economy');
            $demandRange = $tradingConfig['demand_range'] ?? [20, 80];
            $supplyRange = $tradingConfig['supply_range'] ?? [20, 80];

            $demandLevel = rand($demandRange[0], $demandRange[1]);
            $supplyLevel = rand($supplyRange[0], $supplyRange[1]);

            $baseValue = $mineral->base_value ?? 100;
            $demandMultiplier = 1 + (($demandLevel - 50) / 100);
            $supplyMultiplier = 1 - (($supplyLevel - 50) / 100);
            $currentPrice = $baseValue * $demandMultiplier * $supplyMultiplier;

            // Drug Wars-style spike events: random surges/crashes
            $spikeChance = $tradingConfig['spike_chance'] ?? 0.08;
            if (mt_rand(1, 10000) <= (int) ($spikeChance * 10000)) {
                if (rand(0, 1) === 0) {
                    // Surplus/crash: cheap buying opportunity
                    [$crashMin, $crashMax] = $tradingConfig['spike_crash_multiplier'] ?? [0.30, 0.50];
                    $currentPrice *= $crashMin + (mt_rand(0, 1000) / 1000) * ($crashMax - $crashMin);
                } else {
                    // Shortage/surge: sell high opportunity
                    [$surgeMin, $surgeMax] = $tradingConfig['spike_surge_multiplier'] ?? [2.00, 4.00];
                    $currentPrice *= $surgeMin + (mt_rand(0, 1000) / 1000) * ($surgeMax - $surgeMin);
                }
            }

            $spread = $tradingConfig['spread'] ?? 0.08;
            $buyPrice = $currentPrice * (1 - $spread);
            $sellPrice = $currentPrice * (1 + $spread);

            TradingHubInventory::create([
                'trading_hub_id' => $hub->id,
                'mineral_id' => $mineral->id,
                'quantity' => $baseStock,
                'current_price' => $currentPrice,
                'buy_price' => $buyPrice,
                'sell_price' => $sellPrice,
                'demand_level' => $demandLevel,
                'supply_level' => $supplyLevel,
                'last_price_update' => now(),
            ]);
        }

        return true;
    }

    /**
     * Record price sightings for all minerals at a trading hub.
     *
     * Throttled: only records if last sighting for this player+hub was >5 minutes ago.
     *
     * @return bool True if sightings were recorded, false if throttled
     */
    public function recordPriceSightings(Player $player, TradingHub $hub, Collection $inventory): bool
    {
        $lastSighting = PlayerPriceSighting::where('player_id', $player->id)
            ->where('trading_hub_id', $hub->id)
            ->orderByDesc('recorded_at')
            ->first();

        if ($lastSighting && $lastSighting->recorded_at->diffInMinutes(now()) < 5) {
            return false;
        }

        $now = now();
        $sightings = $inventory->map(fn (TradingHubInventory $item) => [
            'player_id' => $player->id,
            'trading_hub_id' => $hub->id,
            'mineral_id' => $item->mineral_id,
            'buy_price' => $item->buy_price,
            'sell_price' => $item->sell_price,
            'quantity' => $item->quantity,
            'recorded_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ])->toArray();

        PlayerPriceSighting::insert($sightings);

        return true;
    }
}
