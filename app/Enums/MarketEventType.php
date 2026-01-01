<?php

namespace App\Enums;

enum MarketEventType: string
{
    case SUPPLY_SHORTAGE = 'supply_shortage';     // Prices spike (2-3x)
    case DEMAND_SPIKE = 'demand_spike';           // Prices spike (2-3x)
    case TRADE_EMBARGO = 'trade_embargo';         // Prices spike, limited stock
    case MARKET_FLOODING = 'market_flooding';     // Prices crash (0.3-0.5x)
    case DISCOVERY = 'discovery';                 // New deposit found, prices crash
    case SPECULATION_BOOM = 'speculation_boom';   // Traders hoard, prices spike
    case MINING_ACCIDENT = 'mining_accident';     // Supply cut, prices spike
    case TECH_BREAKTHROUGH = 'tech_breakthrough'; // Demand surge for specific mineral
    case PIRATE_RAID = 'pirate_raid';            // Supply disrupted, prices spike
    case CORPORATE_BUYOUT = 'corporate_buyout';   // Hoarding, prices spike

    /**
     * Get a random event type
     */
    public static function random(): self
    {
        $cases = self::cases();
        return $cases[array_rand($cases)];
    }

    /**
     * Get the display name for this event type
     */
    public function getDisplayName(): string
    {
        return match($this) {
            self::SUPPLY_SHORTAGE => 'Supply Shortage',
            self::DEMAND_SPIKE => 'Demand Spike',
            self::TRADE_EMBARGO => 'Trade Embargo',
            self::MARKET_FLOODING => 'Market Flooding',
            self::DISCOVERY => 'New Discovery',
            self::SPECULATION_BOOM => 'Speculation Boom',
            self::MINING_ACCIDENT => 'Mining Accident',
            self::TECH_BREAKTHROUGH => 'Tech Breakthrough',
            self::PIRATE_RAID => 'Pirate Raid',
            self::CORPORATE_BUYOUT => 'Corporate Buyout',
        };
    }

    /**
     * Get the price multiplier range for this event type
     */
    public function getPriceMultiplierRange(): array
    {
        return match($this) {
            self::SUPPLY_SHORTAGE => [2.0, 3.0],      // 200-300%
            self::DEMAND_SPIKE => [2.0, 2.5],         // 200-250%
            self::TRADE_EMBARGO => [2.5, 3.5],        // 250-350%
            self::MARKET_FLOODING => [0.3, 0.5],      // 30-50%
            self::DISCOVERY => [0.2, 0.4],            // 20-40%
            self::SPECULATION_BOOM => [1.8, 2.2],     // 180-220%
            self::MINING_ACCIDENT => [2.2, 3.0],      // 220-300%
            self::TECH_BREAKTHROUGH => [2.0, 2.8],    // 200-280%
            self::PIRATE_RAID => [1.8, 2.5],          // 180-250%
            self::CORPORATE_BUYOUT => [2.0, 2.6],     // 200-260%
        };
    }

    /**
     * Get a random multiplier for this event type
     */
    public function getRandomMultiplier(): float
    {
        [$min, $max] = $this->getPriceMultiplierRange();
        return round($min + (($max - $min) * (mt_rand() / mt_getrandmax())), 2);
    }

    /**
     * Is this a price-increasing event?
     */
    public function isPriceIncrease(): bool
    {
        return match($this) {
            self::MARKET_FLOODING, self::DISCOVERY => false,
            default => true,
        };
    }
}
