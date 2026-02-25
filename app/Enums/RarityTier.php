<?php

namespace App\Enums;

enum RarityTier: string
{
    case COMMON = 'common';
    case UNCOMMON = 'uncommon';
    case RARE = 'rare';
    case EPIC = 'epic';
    case UNIQUE = 'unique';
    case EXOTIC = 'exotic';

    /**
     * Weight for weighted random selection (higher = more common).
     */
    public function weight(): int
    {
        return match ($this) {
            self::COMMON => 60,
            self::UNCOMMON => 30,
            self::RARE => 5,
            self::EPIC => 3,
            self::UNIQUE => 2,
            self::EXOTIC => 1,
        };
    }

    /**
     * Stat multiplier applied to base ship/component stats.
     */
    public function statMultiplier(): float
    {
        return match ($this) {
            self::COMMON => 1.0,
            self::UNCOMMON => 1.1,
            self::RARE => 1.25,
            self::EPIC => 1.5,
            self::UNIQUE => 1.8,
            self::EXOTIC => 2.2,
        };
    }

    /**
     * Price multiplier applied to base price.
     */
    public function priceMultiplier(): float
    {
        return match ($this) {
            self::COMMON => 1.0,
            self::UNCOMMON => 1.5,
            self::RARE => 3.0,
            self::EPIC => 6.0,
            self::UNIQUE => 12.0,
            self::EXOTIC => 30.0,
        };
    }

    /**
     * Display color for terminal/UI rendering.
     */
    public function color(): string
    {
        return match ($this) {
            self::COMMON => 'gray',
            self::UNCOMMON => 'green',
            self::RARE => 'blue',
            self::EPIC => 'purple',
            self::UNIQUE => 'orange',
            self::EXOTIC => 'red',
        };
    }

    /**
     * Human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::COMMON => 'Common',
            self::UNCOMMON => 'Uncommon',
            self::RARE => 'Rare',
            self::EPIC => 'Epic',
            self::UNIQUE => 'Unique',
            self::EXOTIC => 'Exotic',
        };
    }
}
