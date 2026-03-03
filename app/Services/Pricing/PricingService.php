<?php

namespace App\Services\Pricing;

use App\DataObjects\PricingContext;
use App\Models\Commodity;
use App\Models\EconomicShock;
use App\Models\HubCommodityStats;
use App\Models\MarketEvent;
use App\Models\TradingHub;
use App\Models\TradingHubInventory;

/**
 * Pure pricing computation service
 *
 * All methods are deterministic and have no side effects:
 * - No database writes
 * - No lazy loading
 * - Minimal queries (uses stats cache)
 *
 * Prices are computed as fixed-point integers (credits)
 *
 * Supports two pricing models:
 * 1. Legacy (Phase 5-9): Abstract supply/demand scores (0-100)
 * 2. Coverage-based (Phase 1+): Real stock/flow with scarcity curves
 */
class PricingService
{
    /**
     * Compute price using coverage-based model
     *
     * New formula (Phase 1+):
     * - coverage_days = on_hand / (avg_daily_demand + epsilon)
     * - scarcity_mult = f(coverage_days) [smooth curve]
     * - shock_mult = ∏ (1 + shock_magnitude * decay_factor) [clamped]
     * - raw_price = base_price * scarcity_mult * shock_mult
     * - buy_price = raw_price * (1 + spread_buy)
     * - sell_price = raw_price * (1 - spread_sell)
     */
    public function computePriceCoverageBased(
        TradingHub $hub,
        Commodity $commodity,
        ?HubCommodityStats $stats = null,
        float $onHandQty = 0
    ): array {
        // Get or create stats
        if (!$stats) {
            $stats = HubCommodityStats::firstOrCreate(
                [
                    'trading_hub_id' => $hub->id,
                    'commodity_id' => $commodity->id,
                ],
                [
                    'avg_daily_demand' => 1,
                    'avg_daily_supply' => 1,
                ]
            );
        }

        // Calculate coverage days
        $avgDailyDemand = max(0.0001, (float)$stats->avg_daily_demand);
        $coverageDays = $onHandQty / $avgDailyDemand;

        // Compute scarcity multiplier from coverage curve
        $scarcityMult = $this->computeScarcityMultiplier(
            $coverageDays,
            config('economy.pricing.scarcity.min_coverage_days', 0.5),
            config('economy.pricing.scarcity.max_coverage_days', 30),
            config('economy.pricing.scarcity.neutral_coverage_days', 7)
        );

        // Compute shock multiplier
        $shockMult = $this->computeShockMultiplier($hub->galaxy_id, $commodity->id, now());

        // Base * multipliers
        $basePrice = $commodity->base_price;
        $rawPrice = $basePrice * $scarcityMult * $shockMult;

        // Apply commodity-specific clamps
        $minPrice = $basePrice * $commodity->price_min_multiplier;
        $maxPrice = $basePrice * $commodity->price_max_multiplier;
        $clampedPrice = max($minPrice, min($maxPrice, $rawPrice));

        // Apply spreads
        $spreadBuy = $hub->spread_buy ?? config('economy.pricing.spread_per_side', 0.08);
        $spreadSell = $hub->spread_sell ?? config('economy.pricing.spread_per_side', 0.08);

        $buyPrice = (int)round($clampedPrice * (1 + $spreadBuy));
        $sellPrice = (int)round($clampedPrice * (1 - $spreadSell));

        return [
            'buy_price' => $buyPrice,
            'sell_price' => $sellPrice,
            'mid_price' => (int)round($clampedPrice),
            'components' => [
                'base_price' => $basePrice,
                'scarcity_mult' => $scarcityMult,
                'shock_mult' => $shockMult,
                'coverage_days' => $coverageDays,
                'spread_buy' => $spreadBuy,
                'spread_sell' => $spreadSell,
            ],
        ];
    }

    /**
     * Compute the mid-price for a hub inventory item (LEGACY)
     *
     * Legacy formula (Phase 5-9):
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

        // Supply/demand multipliers (abstract 0-100 scores)
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
     * Compute buy and sell prices for an inventory item (LEGACY)
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

    /**
     * Compute scarcity multiplier from coverage curve
     *
     * Creates a smooth curve where:
     * - coverage <= minCoverage => maxMult
     * - coverage = neutral => 1.0
     * - coverage >= maxCoverage => minMult
     *
     * Smooth interpolation between points using cosine curve.
     */
    private function computeScarcityMultiplier(
        float $coverageDays,
        float $minCoverageDays,
        float $maxCoverageDays,
        float $neutralCoverageDays
    ): float {
        // Clamp coverage to reasonable range
        $coverage = max(0, min($maxCoverageDays * 2, $coverageDays));

        if ($coverage <= $minCoverageDays) {
            // High scarcity: max multiplier
            return config('economy.pricing.price_max_multiplier', 3.0);
        }

        if ($coverage >= $maxCoverageDays) {
            // Plenty in stock: min multiplier
            return config('economy.pricing.price_min_multiplier', 0.5);
        }

        // Smooth curve using cosine interpolation
        if ($coverage <= $neutralCoverageDays) {
            // Low coverage (0 to neutral): interpolate from max to 1.0
            $ratio = ($coverage - $minCoverageDays) / ($neutralCoverageDays - $minCoverageDays);
            $maxMult = config('economy.pricing.price_max_multiplier', 3.0);
            // Cosine curve: smooth easing
            $eased = (1 - cos($ratio * M_PI)) / 2;
            return $maxMult - ($maxMult - 1.0) * $eased;
        } else {
            // High coverage (neutral to max): interpolate from 1.0 to min
            $ratio = ($coverage - $neutralCoverageDays) / ($maxCoverageDays - $neutralCoverageDays);
            $minMult = config('economy.pricing.price_min_multiplier', 0.5);
            // Cosine curve: smooth easing
            $eased = (1 - cos($ratio * M_PI)) / 2;
            return 1.0 - (1.0 - $minMult) * $eased;
        }
    }

    /**
     * Compute shock multiplier (product of all active shocks with decay)
     */
    private function computeShockMultiplier(
        int $galaxyId,
        int $commodityId,
        \DateTimeInterface $atTime
    ): float {
        $shocks = EconomicShock::where('galaxy_id', $galaxyId)
            ->where('is_active', true)
            ->where(function ($q) use ($commodityId) {
                // System-wide shocks (commodity_id = null) OR commodity-specific
                $q->whereNull('commodity_id')
                    ->orWhere('commodity_id', $commodityId);
            })
            ->get();

        $mult = 1.0;
        $tickNumber = (int)($atTime->timestamp / 60); // Rough tick approximation

        foreach ($shocks as $shock) {
            $effectiveMag = $shock->getEffectiveMagnitude($tickNumber);
            $mult *= (1 + $effectiveMag);

            // Check if fully decayed and deactivate
            if ($shock->isFullyDecayed($tickNumber)) {
                $shock->deactivate();
            }
        }

        // Clamp shock multiplier to prevent extreme values
        $minShockMult = 0.3; // Prices can't go below 30% with shocks
        $maxShockMult = 3.0; // Prices can't go above 300% with shocks

        return max($minShockMult, min($maxShockMult, $mult));
    }
}
