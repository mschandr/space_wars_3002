<?php

namespace App\Services;

use App\Models\MarketEvent;
use App\Models\Mineral;
use App\Models\TradingHub;
use Illuminate\Support\Collection;

class MarketEventService
{
    /**
     * Get all active events affecting a specific mineral at a trading hub
     */
    public function getActiveEvents(?int $mineralId, ?int $tradingHubId): Collection
    {
        return MarketEvent::where('is_active', true)
            ->where('started_at', '<=', now())
            ->where('expires_at', '>', now())
            ->where(function ($query) use ($mineralId) {
                $query->whereNull('mineral_id')
                      ->orWhere('mineral_id', $mineralId);
            })
            ->where(function ($query) use ($tradingHubId) {
                $query->whereNull('trading_hub_id')
                      ->orWhere('trading_hub_id', $tradingHubId);
            })
            ->get();
    }

    /**
     * Get the combined price multiplier for a mineral at a trading hub
     * Multiple events stack multiplicatively (e.g., 2x * 1.5x = 3x total)
     */
    public function getCombinedMultiplier(?int $mineralId, ?int $tradingHubId): float
    {
        $events = $this->getActiveEvents($mineralId, $tradingHubId);

        if ($events->isEmpty()) {
            return 1.0; // No multiplier
        }

        // Stack all multipliers multiplicatively
        $combinedMultiplier = 1.0;
        foreach ($events as $event) {
            $combinedMultiplier *= $event->price_multiplier;
        }

        return round($combinedMultiplier, 2);
    }

    /**
     * Apply event multiplier to a base price
     */
    public function applyEventMultiplier(float $basePrice, ?int $mineralId, ?int $tradingHubId): float
    {
        $multiplier = $this->getCombinedMultiplier($mineralId, $tradingHubId);
        return round($basePrice * $multiplier, 2);
    }

    /**
     * Check if there are any active events for a mineral/hub
     */
    public function hasActiveEvents(?int $mineralId, ?int $tradingHubId): bool
    {
        return $this->getActiveEvents($mineralId, $tradingHubId)->isNotEmpty();
    }

    /**
     * Get all active events for a trading hub (for display)
     */
    public function getActiveEventsForHub(TradingHub $hub): Collection
    {
        return MarketEvent::where('is_active', true)
            ->where('started_at', '<=', now())
            ->where('expires_at', '>', now())
            ->where(function ($query) use ($hub) {
                $query->whereNull('trading_hub_id')
                      ->orWhere('trading_hub_id', $hub->id);
            })
            ->with('mineral')
            ->get();
    }

    /**
     * Deactivate all expired events
     */
    public function deactivateExpiredEvents(): int
    {
        return MarketEvent::where('is_active', true)
            ->where('expires_at', '<=', now())
            ->update(['is_active' => false]);
    }

    /**
     * Get summary of active events (for notifications)
     */
    public function getEventSummary(MarketEvent $event): string
    {
        $mineralName = $event->mineral ? $event->mineral->name : 'All Minerals';
        $hubName = $event->tradingHub ? $event->tradingHub->name : 'Galaxy-Wide';
        $multiplierPercent = (int)(($event->price_multiplier - 1) * 100);

        if ($event->event_type->isPriceIncrease()) {
            return "⚠️  {$event->event_type->getDisplayName()}: {$mineralName} prices UP {$multiplierPercent}% ({$hubName})";
        } else {
            $decreasePercent = abs($multiplierPercent);
            return "📉 {$event->event_type->getDisplayName()}: {$mineralName} prices DOWN {$decreasePercent}% ({$hubName})";
        }
    }
}
