<?php

namespace App\Enums\Crew;

enum CrewAlignment: string
{
    case LAWFUL = 'lawful';
    case NEUTRAL = 'neutral';
    case SHADY = 'shady';

    /**
     * Get human-readable label for this alignment
     */
    public function label(): string
    {
        return match ($this) {
            self::LAWFUL => 'Lawful',
            self::NEUTRAL => 'Neutral',
            self::SHADY => 'Shady',
        };
    }

    /**
     * Get the shady score contribution for this alignment
     * Used to compute overall ship persona shady_score
     * lawful = -1, neutral = 0, shady = +1
     */
    public function shadyScore(): int
    {
        return match ($this) {
            self::LAWFUL => -1,
            self::NEUTRAL => 0,
            self::SHADY => 1,
        };
    }

    /**
     * Check if this alignment is considered "shady"
     */
    public function isShady(): bool
    {
        return $this === self::SHADY;
    }

    /**
     * Check if this alignment can access black market
     * Neutral and shady crew can perceive black market
     */
    public function canAccessBlackMarket(): bool
    {
        return $this !== self::LAWFUL;
    }
}
