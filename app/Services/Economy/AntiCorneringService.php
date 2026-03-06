<?php

namespace App\Services\Economy;

use App\Models\Player;
use App\Models\TradingHubInventory;
use Illuminate\Support\Facades\DB;

/**
 * Anti-cornering mechanics prevent market manipulation through:
 * - Volume-based fees (spreads widen for large purchases)
 * - Purchase limits per tick
 * - Ensures no single player can monopolize a commodity
 */
class AntiCorneringService
{
    /**
     * Compute additional spread based on purchase volume
     *
     * @param  float  $requestedQty  Quantity requested by player (must be >= 0)
     * @param  ?TradingHubInventory  $inventory  Optional inventory context (unused in v1)
     * @return float Additional spread to apply (0.0 to max_additional_spread)
     *
     * @throws \InvalidArgumentException if requestedQty is negative
     */
    public function computeVolumeAdjustment(
        float $requestedQty,
        ?TradingHubInventory $inventory = null
    ): float {
        // Validate quantity is non-negative
        if ($requestedQty < 0) {
            throw new \InvalidArgumentException("Quantity cannot be negative. Got: {$requestedQty}");
        }

        if (! config('economy.anti_cornering.enabled', false)) {
            return 0.0;
        }

        $config = config('economy.anti_cornering.volume_fee');

        // Validate config values are positive
        $threshold = max(0, (float) $config['threshold']);
        $feePerUnit = max(0, (float) $config['fee_per_unit']);
        $maxSpread = max(0, (float) $config['max_additional_spread']);

        if ($requestedQty <= $threshold) {
            return 0.0;  // No additional fee below threshold
        }

        $unitsAboveThreshold = $requestedQty - $threshold;
        $additionalSpread = $unitsAboveThreshold * $feePerUnit;

        // Cap the spread to prevent extreme prices
        return min($additionalSpread, $maxSpread);
    }

    /**
     * Check if player has hit purchase limit this tick/day
     *
     * @param  Player  $player  Player attempting purchase
     * @param  float  $requestedQty  Quantity they want to purchase (must be >= 0)
     * @return bool True if purchase is allowed, false if limit exceeded
     *
     * @throws \InvalidArgumentException if requestedQty is negative
     */
    public function canPurchaseThisTick(
        Player $player,
        float $requestedQty
    ): bool {
        // Validate quantity is non-negative
        if ($requestedQty < 0) {
            throw new \InvalidArgumentException("Quantity cannot be negative. Got: {$requestedQty}");
        }

        if (! config('economy.anti_cornering.enabled', false)) {
            return true;
        }

        $maxPerTick = config('economy.anti_cornering.max_purchase_per_tick');

        if ($maxPerTick === null || $maxPerTick <= 0) {
            return true;  // No limit configured or invalid config
        }

        // Query ledger for player's total purchases today
        $purchasedThisTick = DB::table('commodity_ledger_entries')
            ->where('actor_id', $player->id) // @phpstan-ignore-line
            ->where('actor_type', 'PLAYER')
            ->where('reason_code', 'TRADE_BUY')
            ->whereDate('timestamp', today())
            ->sum('qty_delta');  // Sum is negative for buys; need absolute value

        $purchasedThisTick = abs($purchasedThisTick ?? 0);

        return ($purchasedThisTick + $requestedQty) <= $maxPerTick;
    }

    /**
     * Get human-readable reason if purchase cannot proceed
     *
     * @param  float  $requestedQty  Quantity they want to purchase (must be >= 0)
     * @return string|null Error message, or null if purchase is allowed
     *
     * @throws \InvalidArgumentException if requestedQty is negative
     */
    public function getPurchaseBlockReason(
        Player $player,
        float $requestedQty
    ): ?string {
        // Validate quantity is non-negative
        if ($requestedQty < 0) {
            throw new \InvalidArgumentException("Quantity cannot be negative. Got: {$requestedQty}");
        }

        if (! config('economy.anti_cornering.enabled', false)) {
            return null;
        }

        if (! $this->canPurchaseThisTick($player, $requestedQty)) {
            $maxPerTick = config('economy.anti_cornering.max_purchase_per_tick');
            $purchasedToday = abs(
                DB::table('commodity_ledger_entries')
                    ->where('actor_id', $player->id) // @phpstan-ignore-line
                    ->where('actor_type', 'PLAYER')
                    ->where('reason_code', 'TRADE_BUY')
                    ->whereDate('timestamp', today())
                    ->sum('qty_delta') ?? 0
            );

            return "Purchase limit exceeded. You've purchased {$purchasedToday} units today. "
                 ."Daily limit: {$maxPerTick} units.";
        }

        return null;
    }
}
