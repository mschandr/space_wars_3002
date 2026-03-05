<?php

namespace App\DataObjects;

use App\Models\TradingHub;
use App\Services\MarketEventService;

readonly class PricingContext
{
    public function __construct(
        public float $spread,
        public float $eventMultiplier,
        public bool $isMirrorUniverse,
        public float $mirrorBoost,
        public float $portTypeBias = 1.0,
    ) {}

    /**
     * Factory method to create a PricingContext for a trading hub
     */
    public static function forHub(TradingHub $hub): self
    {
        // Get spread from config
        $spread = config('economy.pricing.spread_per_side', 0.08);

        // For now, event multiplier is always 1.0 (per-mineral events handled separately)
        // This will be extended later for hub-wide events
        $eventMultiplier = 1.0;

        // Check if in mirror universe
        $isMirrorUniverse = $hub->pointOfInterest?->galaxy?->isMirrorUniverse() ?? false;
        $mirrorBoost = $isMirrorUniverse ? config('game_config.mirror_universe.price_boost', 1.5) : 1.0;

        return new self(
            spread: $spread,
            eventMultiplier: $eventMultiplier,
            isMirrorUniverse: $isMirrorUniverse,
            mirrorBoost: $mirrorBoost,
        );
    }
}
