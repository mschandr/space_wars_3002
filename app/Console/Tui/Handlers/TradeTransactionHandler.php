<?php

namespace App\Console\Tui\Handlers;

use App\Models\Player;
use App\Models\PlayerCargo;
use App\Models\TradingHub;
use App\Models\TradingHubInventory;

class TradeTransactionHandler
{
    public function __construct(private Player $player)
    {
    }

    /**
     * Execute a buy transaction
     */
    public function executeBuy(TradingHubInventory $inventory, int $quantity): array
    {
        $ship = $this->player->activeShip;
        $mineral = $inventory->mineral;

        // Validate
        $error = $this->validateBuyTransaction($inventory, $quantity, $ship);
        if ($error) {
            return [
                'success' => false,
                'message' => $error,
            ];
        }

        $totalCost = $inventory->sell_price * $quantity;

        // Execute transaction
        $this->player->deductCredits($totalCost);
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

        return [
            'success' => true,
            'message' => "Purchased {$quantity} units of {$mineral->name} for " . number_format($totalCost, 2) . " credits",
            'quantity' => $quantity,
            'cost' => $totalCost,
            'mineral' => $mineral->name,
        ];
    }

    /**
     * Execute a sell transaction
     */
    public function executeSell(PlayerCargo $cargo, TradingHub $hub, int $quantity): array
    {
        $ship = $this->player->activeShip;
        $mineral = $cargo->mineral;

        // Validate
        $error = $this->validateSellTransaction($cargo, $quantity);
        if ($error) {
            return [
                'success' => false,
                'message' => $error,
            ];
        }

        // Get or create hub inventory
        $hubInventory = TradingHubInventory::firstOrNew([
            'trading_hub_id' => $hub->id,
            'mineral_id' => $mineral->id,
        ]);

        if (!$hubInventory->exists) {
            $hubInventory->quantity = 0;
            $hubInventory->demand_level = 50;
            $hubInventory->supply_level = 50;
            $hubInventory->save();
            $hubInventory->updatePricing();
            $hubInventory->refresh();
        }

        $totalRevenue = $hubInventory->buy_price * $quantity;

        // Execute transaction
        $this->player->addCredits($totalRevenue);
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

        return [
            'success' => true,
            'message' => "Sold {$quantity} units of {$mineral->name} for " . number_format($totalRevenue, 2) . " credits",
            'quantity' => $quantity,
            'revenue' => $totalRevenue,
            'mineral' => $mineral->name,
        ];
    }

    /**
     * Validate buy transaction
     */
    private function validateBuyTransaction(
        TradingHubInventory $inventory,
        int $quantity,
        $ship
    ): ?string {
        if (!$inventory->hasStock($quantity)) {
            return "Insufficient stock available";
        }

        $totalCost = $inventory->sell_price * $quantity;
        if ($this->player->credits < $totalCost) {
            return "Insufficient credits! Need " . number_format($totalCost, 2);
        }

        $availableSpace = $ship->cargo_hold - $ship->current_cargo;
        if ($availableSpace < $quantity) {
            return "Insufficient cargo space! Need {$quantity}, have {$availableSpace}";
        }

        return null;
    }

    /**
     * Validate sell transaction
     */
    private function validateSellTransaction(PlayerCargo $cargo, int $quantity): ?string
    {
        if ($cargo->quantity < $quantity) {
            return "You don't have that many units! Have: {$cargo->quantity}";
        }

        return null;
    }
}
