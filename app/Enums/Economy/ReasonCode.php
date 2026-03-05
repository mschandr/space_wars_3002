<?php

namespace App\Enums\Economy;

enum ReasonCode: string
{
    case GENESIS = 'GENESIS';              // Initial ledger backfill
    case MINING = 'MINING';                // Extracted from deposit
    case CONSTRUCTION = 'CONSTRUCTION';    // Consumed in blueprint
    case UPKEEP = 'UPKEEP';                // Consumed by maintenance/habitats
    case TRADE_BUY = 'TRADE_BUY';         // Bought by player
    case TRADE_SELL = 'TRADE_SELL';       // Sold by player
    case SALVAGE = 'SALVAGE';             // Recovered from wreck
    case NPC_INJECT = 'NPC_INJECT';       // NPC added to market
    case NPC_CONSUME = 'NPC_CONSUME';     // NPC bought/used

    public function label(): string
    {
        return match ($this) {
            self::GENESIS => 'Genesis (Initial backfill)',
            self::MINING => 'Mining',
            self::CONSTRUCTION => 'Construction',
            self::UPKEEP => 'Upkeep/Maintenance',
            self::TRADE_BUY => 'Trade (Buy)',
            self::TRADE_SELL => 'Trade (Sell)',
            self::SALVAGE => 'Salvage',
            self::NPC_INJECT => 'NPC Supply',
            self::NPC_CONSUME => 'NPC Demand',
        };
    }

    public function isSource(): bool
    {
        return in_array($this, [self::GENESIS, self::MINING, self::SALVAGE, self::NPC_INJECT, self::TRADE_SELL]);
    }

    public function isSink(): bool
    {
        return in_array($this, [self::CONSTRUCTION, self::UPKEEP, self::TRADE_BUY, self::NPC_CONSUME]);
    }

    public function isTrade(): bool
    {
        return in_array($this, [self::TRADE_BUY, self::TRADE_SELL]);
    }
}
