<?php

namespace App\Services;

use App\Enums\MarketEventType;
use App\Models\MarketEvent;
use App\Models\Mineral;
use App\Models\TradingHub;
use Illuminate\Support\Str;

class MarketEventGenerator
{
    /**
     * Generate multiple events at once (for testing or initial population)
     */
    public function generateMultipleEvents(int $count, float $probability = 1.0): int
    {
        $generated = 0;

        for ($i = 0; $i < $count; $i++) {
            if ($this->generateRandomEvent($probability) !== null) {
                $generated++;
            }
        }

        return $generated;
    }

    /**
     * Generate a random market event
     *
     * @param  float  $probability  Probability of generating an event (0.0-1.0)
     */
    public function generateRandomEvent(float $probability = 0.15): ?MarketEvent
    {
        // Roll for event occurrence
        if (mt_rand() / mt_getrandmax() > $probability) {
            return null; // No event this time
        }

        // Get random event type
        $eventType = MarketEventType::random();

        // Decide scope: specific mineral or all minerals (70% specific, 30% all)
        $mineralId = (mt_rand(1, 100) <= 70) ? $this->getRandomMineralId() : null;

        // Decide scope: specific hub or galaxy-wide (80% specific, 20% galaxy)
        $tradingHubId = (mt_rand(1, 100) <= 80) ? $this->getRandomTradingHubId() : null;

        // Get multiplier for this event type
        $multiplier = $eventType->getRandomMultiplier();

        // Generate description
        $description = $this->generateDescription($eventType, $mineralId);

        // Duration: 30 minutes to 4 hours
        $durationMinutes = mt_rand(30, 240);
        $startedAt = now();
        $expiresAt = now()->addMinutes($durationMinutes);

        return MarketEvent::create([
            'uuid' => Str::uuid(),
            'mineral_id' => $mineralId,
            'trading_hub_id' => $tradingHubId,
            'event_type' => $eventType,
            'price_multiplier' => $multiplier,
            'description' => $description,
            'started_at' => $startedAt,
            'expires_at' => $expiresAt,
            'is_active' => true,
        ]);
    }

    /**
     * Get a random mineral ID
     */
    private function getRandomMineralId(): ?int
    {
        return Mineral::inRandomOrder()->first()?->id;
    }

    /**
     * Get a random trading hub ID
     */
    private function getRandomTradingHubId(): ?int
    {
        return TradingHub::where('is_active', true)->inRandomOrder()->first()?->id;
    }

    /**
     * Generate a description for the event
     */
    private function generateDescription(MarketEventType $eventType, ?int $mineralId): string
    {
        $mineralName = $mineralId ? (Mineral::find($mineralId)?->name ?? 'Unknown') : 'various minerals';

        return match ($eventType) {
            MarketEventType::SUPPLY_SHORTAGE => "⚠️  BREAKING: Supply shortage of {$mineralName} reported across multiple sectors!",
            MarketEventType::DEMAND_SPIKE => "📈 MARKET ALERT: Massive demand surge for {$mineralName} from industrial consortiums!",
            MarketEventType::TRADE_EMBARGO => "🚨 EMBARGO: Government restrictions imposed on {$mineralName} trade!",
            MarketEventType::MARKET_FLOODING => "📉 CRASH: Market flooded with {$mineralName} as reserves dumped!",
            MarketEventType::DISCOVERY => "🎉 DISCOVERY: New rich deposits of {$mineralName} found in outer sectors!",
            MarketEventType::SPECULATION_BOOM => "💰 SPECULATION: Traders hoarding {$mineralName} expecting price surge!",
            MarketEventType::MINING_ACCIDENT => "💥 DISASTER: Mining accident severely limits {$mineralName} production!",
            MarketEventType::TECH_BREAKTHROUGH => "🔬 BREAKTHROUGH: New technology requires massive quantities of {$mineralName}!",
            MarketEventType::PIRATE_RAID => "☠️  PIRATE ATTACK: Raiders disrupt {$mineralName} supply lanes!",
            MarketEventType::CORPORATE_BUYOUT => "🏢 BUYOUT: Mega-corp monopolizing {$mineralName} supplies!",
        };
    }
}
