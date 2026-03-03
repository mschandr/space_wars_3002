<?php

namespace App\Enums\Economy;

enum CommodityCategory: string
{
    case MINERAL = 'MINERAL';
    case EXOTIC = 'EXOTIC';
    case SOFT = 'SOFT';

    public function label(): string
    {
        return match ($this) {
            self::MINERAL => 'Mineral',
            self::EXOTIC => 'Exotic',
            self::SOFT => 'Service',
        };
    }

    public function isConserved(): bool
    {
        return in_array($this, [self::MINERAL, self::EXOTIC]);
    }

    public function description(): string
    {
        return match ($this) {
            self::MINERAL => 'Standard minerals and ores',
            self::EXOTIC => 'Rare exotics and rare materials',
            self::SOFT => 'Services (repairs, labor, etc.)',
        };
    }
}
