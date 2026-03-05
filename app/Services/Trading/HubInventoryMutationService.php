<?php

namespace App\Services\Trading;

use App\DataObjects\PricingContext;
use App\Models\MarketEvent;
use App\Models\TradingHubInventory;
use App\Services\Pricing\PricingService;

/**
 * Applies supply/demand mutations to hub inventory and recalculates prices
 *
 * Key responsibility: Single database save per trade
 * - Mutate supply/demand levels based on trade direction
 * - Recompute prices via PricingService
 * - Save all changes in one transaction
 *
 * Supply/demand mutation rules:
 * - Each trade direction moves BOTH supply AND demand
 * - Buy: quantity -= amount, demand_level += step, supply_level -= step
 * - Sell: quantity += amount, supply_level += step, demand_level -= step
 * - This prevents the "convergence to 75%" bug in the old system
 *
 * Clamping: supply/demand stay in [0, 100]
 */
class HubInventoryMutationService
{
    public function __construct(
        private readonly PricingService $pricingService
    ) {}

    /**
     * Apply a trade to hub inventory, mutating supply/demand and recalculating prices
     *
     * @param TradingHubInventory $inv Inventory to mutate (must have mineral loaded)
     * @param int $amount Quantity traded (must be > 0)
     * @param string $direction 'buy' or 'sell'
     * @param PricingContext $ctx Pricing context for recomputation
     * @param ?MarketEvent $event Optional market event affecting this item
     * @return void (inventory is mutated in-place and saved)
     * @throws \InvalidArgumentException if amount <= 0 or direction is invalid
     */
    public function applyTrade(
        TradingHubInventory $inv,
        int $amount,
        string $direction,
        PricingContext $ctx,
        ?MarketEvent $event = null
    ): void {
        // Validate direction
        if (!in_array($direction, ['buy', 'sell'])) {
            throw new \InvalidArgumentException("Direction must be 'buy' or 'sell', got: $direction");
        }

        // Validate amount is positive
        if ($amount <= 0) {
            throw new \InvalidArgumentException("Amount must be positive, got: $amount");
        }

        // Get units_per_step config with validation
        $unitsPerStep = (int) config('economy.pricing.units_per_step', 10);

        // Ensure units_per_step is positive (coerce invalid values to 1)
        if ($unitsPerStep <= 0) {
            $unitsPerStep = 1;
        }

        // Compute integer step for supply/demand mutation (guaranteed > 0)
        $step = max(1, intdiv($amount, $unitsPerStep));

        // Mutate in memory (update on_hand_qty, the ledger-backed source of truth)
        if ($direction === 'buy') {
            // Player buys FROM hub: hub quantity decreases
            $inv->on_hand_qty -= $amount;
            $inv->demand_level = min(100, $inv->demand_level + $step);
            $inv->supply_level = max(0, $inv->supply_level - $step);
        } else {
            // Player sells TO hub: hub quantity increases
            $inv->on_hand_qty += $amount;
            $inv->supply_level = min(100, $inv->supply_level + $step);
            $inv->demand_level = max(0, $inv->demand_level - $step);
        }

        // Keep legacy quantity column in sync for backward compatibility
        $inv->quantity = (int) $inv->on_hand_qty;
        $inv->last_snapshot_at = now();

        // Recompute prices
        [$buyPrice, $sellPrice] = $this->pricingService->computeBuySellPrices($inv, $ctx, $event);
        $inv->current_price = $this->pricingService->computePrice($inv, $ctx, $event);
        $inv->buy_price = $buyPrice;
        $inv->sell_price = $sellPrice;
        $inv->last_price_update = now();

        // Single save
        $inv->save();
    }
}
