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
     * @param int $amount Quantity traded
     * @param string $direction 'buy' or 'sell'
     * @param PricingContext $ctx Pricing context for recomputation
     * @param ?MarketEvent $event Optional market event affecting this item
     * @return void (inventory is mutated in-place and saved)
     */
    public function applyTrade(
        TradingHubInventory $inv,
        int $amount,
        string $direction,
        PricingContext $ctx,
        ?MarketEvent $event = null
    ): void {
        if (!in_array($direction, ['buy', 'sell'])) {
            throw new \InvalidArgumentException("Direction must be 'buy' or 'sell', got: $direction");
        }

        // Compute integer step for supply/demand mutation
        $unitsPerStep = config('economy.pricing.units_per_step', 10);
        $step = max(1, intdiv($amount, $unitsPerStep));

        // Mutate in memory
        if ($direction === 'buy') {
            // Player buys FROM hub
            $inv->quantity -= $amount;
            $inv->demand_level = min(100, $inv->demand_level + $step);
            $inv->supply_level = max(0, $inv->supply_level - $step);
        } else {
            // Player sells TO hub
            $inv->quantity += $amount;
            $inv->supply_level = min(100, $inv->supply_level + $step);
            $inv->demand_level = max(0, $inv->demand_level - $step);
        }

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
