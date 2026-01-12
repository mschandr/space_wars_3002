<?php

namespace App\Services;

use App\Models\Player;
use App\Models\PlayerCargo;
use App\Models\PlayerShip;
use App\Models\TradingHubInventory;

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
        $totalCost = $inventory->sell_price * $quantity;

        // Validations
        if (! $inventory->hasStock($quantity)) {
            return [
                'success' => false,
                'message' => 'Insufficient stock available',
                'xp_earned' => 0,
            ];
        }

        if ($player->credits < $totalCost) {
            return [
                'success' => false,
                'message' => 'Insufficient credits',
                'xp_earned' => 0,
            ];
        }

        $availableSpace = $ship->cargo_hold - $ship->current_cargo;
        if ($availableSpace < $quantity) {
            return [
                'success' => false,
                'message' => 'Insufficient cargo space',
                'xp_earned' => 0,
            ];
        }

        // Execute transaction
        $player->deductCredits($totalCost);
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
        $xpEarned = (int) max(5, $quantity / 10); // 1 XP per 10 units, min 5
        $player->addExperience($xpEarned);

        return [
            'success' => true,
            'message' => "Successfully purchased {$quantity} units of {$mineral->name}",
            'total_cost' => $totalCost,
            'xp_earned' => $xpEarned,
        ];
    }

    /**
     * Sell minerals to a trading hub
     *
     * @return array ['success' => bool, 'message' => string, 'xp_earned' => int]
     */
    public function sellMineral(Player $player, PlayerShip $ship, PlayerCargo $cargo, TradingHubInventory $hubInventory, int $quantity): array
    {
        $mineral = $cargo->mineral;

        // Validations
        if ($cargo->quantity < $quantity) {
            return [
                'success' => false,
                'message' => 'You don\'t have that many units',
                'xp_earned' => 0,
            ];
        }

        $totalRevenue = $hubInventory->buy_price * $quantity;

        // Execute transaction
        $player->addCredits($totalRevenue);
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
        $xpEarned = (int) max(10, $totalRevenue / 100); // 1 XP per 100 credits, min 10
        $player->addExperience($xpEarned);

        return [
            'success' => true,
            'message' => "Successfully sold {$quantity} units of {$mineral->name}",
            'total_revenue' => $totalRevenue,
            'xp_earned' => $xpEarned,
        ];
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
}
