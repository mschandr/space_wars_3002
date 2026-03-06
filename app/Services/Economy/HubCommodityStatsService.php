<?php

namespace App\Services\Economy;

use App\Enums\Economy\ReasonCode;
use App\Models\Commodity;
use App\Models\Galaxy;
use App\Models\HubCommodityStats;
use App\Models\TradingHub;
use Illuminate\Database\Eloquent\Collection;

/**
 * HubCommodityStatsService
 *
 * Computes rolling average demand and supply from ledger entries.
 * Called once per tick to update cached stats for all hubs/commodities.
 *
 * Key concept:
 * - Daily demand = sum of TRADE_BUY + CONSTRUCTION + UPKEEP over window
 * - Daily supply = sum of MINING + SALVAGE + NPC_INJECT over window
 * - These feed into PricingService for coverage-based pricing
 */
class HubCommodityStatsService
{
    /**
     * Recompute stats for a single hub+commodity pair
     *
     * @param int $windowDays Days of history to include (default: 7 days)
     */
    public function computeStats(
        TradingHub $hub,
        Commodity $commodity,
        int $windowDays = 7
    ): void {
        $since = now()->subDays($windowDays);

        // Query ledger for this hub+commodity
        $entries = \DB::table('commodity_ledger_entries')
            ->where('trading_hub_id', $hub->id)
            ->where('commodity_id', $commodity->id)
            ->where('timestamp', '>=', $since)
            ->get();

        // Calculate demand (consumption)
        $demandQty = $entries
            ->whereIn('reason_code', [
                ReasonCode::TRADE_BUY->value,
                ReasonCode::CONSTRUCTION->value,
                ReasonCode::UPKEEP->value,
                ReasonCode::NPC_CONSUME->value,
            ])
            ->sum(function ($entry) {
                return abs((float)$entry->qty_delta);
            });

        // Calculate supply (production)
        $supplyQty = $entries
            ->whereIn('reason_code', [
                ReasonCode::MINING->value,
                ReasonCode::SALVAGE->value,
                ReasonCode::NPC_INJECT->value,
            ])
            ->sum(function ($entry) {
                return max(0, (float)$entry->qty_delta);
            });

        // Convert to daily average
        $avgDailyDemand = $demandQty / $windowDays;
        $avgDailySupply = $supplyQty / $windowDays;

        // Update or create stats
        HubCommodityStats::updateOrCreate(
            [
                'trading_hub_id' => $hub->id,
                'commodity_id' => $commodity->id,
            ],
            [
                'avg_daily_demand' => $avgDailyDemand,
                'avg_daily_supply' => $avgDailySupply,
                'last_computed_at' => now(),
            ]
        );
    }

    /**
     * Recompute all stats in a galaxy
     *
     * Call this once per tick to update everything.
     */
    public function recomputeGalaxyStats(
        Galaxy $galaxy,
        int $windowDays = 7
    ): array {
        $hubs = $galaxy->tradingHubs()->get();
        $commodities = Commodity::all();

        $stats = ['computed' => 0, 'errors' => []];

        foreach ($hubs as $hub) {
            foreach ($commodities as $commodity) {
                try {
                    $this->computeStats($hub, $commodity, $windowDays);
                    $stats['computed']++;
                } catch (\Exception $e) {
                    $stats['errors'][] = "Hub {$hub->id}, Commodity {$commodity->id}: {$e->getMessage()}";
                }
            }
        }

        return $stats;
    }

    /**
     * Recompute all stats across all galaxies
     *
     * Used in background tick job.
     */
    public function recomputeAllStats(int $windowDays = 7): array {
        $galaxies = Galaxy::all();
        $totalStats = ['computed' => 0, 'galaxies' => 0, 'errors' => []];

        foreach ($galaxies as $galaxy) {
            $stats = $this->recomputeGalaxyStats($galaxy, $windowDays);
            $totalStats['computed'] += $stats['computed'];
            $totalStats['galaxies']++;
            $totalStats['errors'] = array_merge($totalStats['errors'], $stats['errors']);
        }

        return $totalStats;
    }

    /**
     * Get current stats for a hub+commodity
     */
    public function getStats(
        TradingHub $hub,
        Commodity $commodity
    ): ?HubCommodityStats {
        return HubCommodityStats::where('trading_hub_id', $hub->id)
            ->where('commodity_id', $commodity->id)
            ->first();
    }

    /**
     * Get or create with defaults
     */
    public function getOrCreateStats(
        TradingHub $hub,
        Commodity $commodity
    ): HubCommodityStats {
        return HubCommodityStats::firstOrCreate(
            [
                'trading_hub_id' => $hub->id,
                'commodity_id' => $commodity->id,
            ],
            [
                'avg_daily_demand' => 0,
                'avg_daily_supply' => 0,
                'last_computed_at' => now(),
            ]
        );
    }

    /**
     * Check if stats need recomputation
     *
     * Returns true if last_computed_at is older than interval.
     */
    public function needsRecompute(
        TradingHub $hub,
        Commodity $commodity,
        int $intervalMinutes = 60
    ): bool {
        $stats = $this->getStats($hub, $commodity);

        if (!$stats || !$stats->last_computed_at) {
            return true;
        }

        return $stats->last_computed_at->diffInMinutes(now()) >= $intervalMinutes;
    }

    /**
     * Get all stats for a hub
     */
    public function getHubStats(TradingHub $hub): Collection {
        return HubCommodityStats::where('trading_hub_id', $hub->id)
            ->with('commodity')
            ->get();
    }

    /**
     * Calculate coverage days for diagnostics
     *
     * coverage_days = on_hand / (avg_daily_demand + epsilon)
     */
    public function calculateCoverageDays(
        TradingHub $hub,
        Commodity $commodity
    ): float {
        $inventoryService = app(InventoryService::class);
        $onHand = $inventoryService->getOnHand($hub, $commodity);

        $stats = $this->getStats($hub, $commodity);
        $avgDailyDemand = $stats?->avg_daily_demand ?? 1;

        // Add epsilon to prevent division by zero
        return $onHand / (max(0.0001, $avgDailyDemand));
    }

    /**
     * Get detailed breakdown for diagnostics
     */
    public function getDetailedAnalysis(
        TradingHub $hub,
        Commodity $commodity,
        int $windowDays = 7
    ): array {
        $stats = $this->getStats($hub, $commodity);
        $inventoryService = app(InventoryService::class);

        return [
            'hub' => ['id' => $hub->id, 'name' => $hub->name],
            'commodity' => ['id' => $commodity->id, 'code' => $commodity->code],
            'on_hand_qty' => $inventoryService->getOnHand($hub, $commodity),
            'reserved_qty' => $inventoryService->getReserved($hub, $commodity),
            'available_qty' => $inventoryService->getAvailable($hub, $commodity),
            'avg_daily_demand' => $stats?->avg_daily_demand ?? 0,
            'avg_daily_supply' => $stats?->avg_daily_supply ?? 0,
            'coverage_days' => $this->calculateCoverageDays($hub, $commodity),
            'last_computed_at' => $stats?->last_computed_at,
            'window_days' => $windowDays,
        ];
    }
}
