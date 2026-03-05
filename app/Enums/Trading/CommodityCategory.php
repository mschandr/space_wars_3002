<?php

namespace App\Enums\Trading;

enum CommodityCategory: string
{
    case CIVILIAN = 'civilian';
    case INDUSTRIAL = 'industrial';
    case BLACK = 'black';

    /**
     * Get human-readable label for this category
     */
    public function label(): string
    {
        return match ($this) {
            self::CIVILIAN => 'Civilian',
            self::INDUSTRIAL => 'Industrial',
            self::BLACK => 'Black Market',
        };
    }

    /**
     * Check if this category has restrictions
     */
    public function isRestricted(): bool
    {
        return $this !== self::CIVILIAN;
    }

    /**
     * Get the minimum reputation required to access this category
     * (null means unrestricted)
     */
    public function minReputation(): ?int
    {
        return match ($this) {
            self::CIVILIAN => null,
            self::INDUSTRIAL => null,
            self::BLACK => config('economy.black_market.access_rules.min_reputation'),
        };
    }
}
