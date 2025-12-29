<?php

namespace App\Enums\Trading;

enum MineralRarity: string
{
    case ABUNDANT = 'abundant';
    case COMMON = 'common';
    case UNCOMMON = 'uncommon';
    case RARE = 'rare';
    case VERY_RARE = 'very_rare';
    case EPIC = 'epic';
    case LEGENDARY = 'legendary';
    case MYTHIC = 'mythic';

    public function label(): string
    {
        return match($this) {
            self::ABUNDANT => 'Abundant',
            self::COMMON => 'Common',
            self::UNCOMMON => 'Uncommon',
            self::RARE => 'Rare',
            self::VERY_RARE => 'Very Rare',
            self::EPIC => 'Epic',
            self::LEGENDARY => 'Legendary',
            self::MYTHIC => 'Mythic',
        };
    }

    public function valueMultiplier(): float
    {
        return match($this) {
            self::ABUNDANT => 0.5,
            self::COMMON => 1.0,
            self::UNCOMMON => 2.5,
            self::RARE => 5.0,
            self::VERY_RARE => 10.0,
            self::EPIC => 25.0,
            self::LEGENDARY => 50.0,
            self::MYTHIC => 100.0,
        };
    }
}
