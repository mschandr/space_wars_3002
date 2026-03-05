<?php

namespace App\Services\Pricing;

use App\DataObjects\PricingContext;
use App\Models\MarketEvent;
use App\Models\TradingHubInventory;

/**
 * Pure pricing computation service
 *
 * All methods are deterministic and have no side effects:
 * - No database writes
 * - No lazy loading
 * - No service locator calls
 *
 * Prices are computed as fixed-point integers (credits)
 */
class PricingService
{
    /**
     * Compute the mid-price for a hub inventory item
     *
     * Formula:
     * - demandMultiplier  = 1 + ((demand_level - 50) / 100)
     * - supplyMultiplier  = 1 - ((supply_level - 50) / 100)
     * - rawPrice          = mineral.base_value * demandMultiplier * supplyMultiplier
     * - priceWithEvents   = rawPrice * ctx.eventMultiplier
     * - priceWithMirror   = priceWithEvents * (ctx.isMirrorUniverse ? ctx.mirrorBoost : 1.0)
     * - clamped           = clamp(priceWithMirror, base_value * min_mult, base_value * max_mult)
     * - result            = (int) round(clamped)
     *
     * @param TradingHubInventory $inv Inventory with mineral loaded
     * @param PricingContext $ctx Pricing context with spread and modifiers
     * @param ?MarketEvent $event Optional market event affecting this specific mineral
     * @return int Mid-price in credits (integer)
     */
    public function computePrice(
        TradingHubInventory $inv,
        PricingContext $ctx,
        ?MarketEvent $event = null
    ): int {
        $mineral = $inv->mineral;
        $baseValue = $mineral->base_value;

        // Supply/demand multipliers
        $demandMultiplier = 1 + (($inv->demand_level - 50) / 100);
        $supplyMultiplier = 1 - (($inv->supply_level - 50) / 100);

        // Base price from supply/demand
        $rawPrice = $baseValue * $demandMultiplier * $supplyMultiplier;

        // Apply context event multiplier
        $priceWithEvents = $rawPrice * $ctx->eventMultiplier;

        // Apply per-item market event if provided
        if ($event) {
            $priceWithEvents *= $event->price_multiplier;
        }

        // Apply mirror universe boost
        $priceWithMirror = $ctx->isMirrorUniverse ? $priceWithEvents * $ctx->mirrorBoost : $priceWithEvents;

        // Clamp to config min/max
        $minMultiplier = config('economy.pricing.min_multiplier', 0.10);
        $maxMultiplier = config('economy.pricing.max_multiplier', 10.00);

        $minPrice = $baseValue * $minMultiplier;
        $maxPrice = $baseValue * $maxMultiplier;

        $clamped = max($minPrice, min($maxPrice, $priceWithMirror));

        // Round to integer credits
        return (int) round($clamped);
    }

    /**
     * Compute buy and sell prices for an inventory item
     *
     * Returns [buy_price, sell_price] where:
     * - buy_price  = mid_price * (1 - spread)
     * - sell_price = mid_price * (1 + spread)
     *
     * @param TradingHubInventory $inv Inventory with mineral loaded
     * @param PricingContext $ctx Pricing context
     * @param ?MarketEvent $event Optional market event
     * @return array [buy_price, sell_price] both integers
     */
    public function computeBuySellPrices(
        TradingHubInventory $inv,
        PricingContext $ctx,
        ?MarketEvent $event = null
    ): array {
        $midPrice = $this->computePrice($inv, $ctx, $event);

        $buyPrice = (int) round($midPrice * (1 - $ctx->spread));
        $sellPrice = (int) round($midPrice * (1 + $ctx->spread));

        return [$buyPrice, $sellPrice];
    }
}
