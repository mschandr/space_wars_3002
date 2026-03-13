<?php

namespace App\Enums\Vendor;

enum TransactionContext: string
{
    case NEUTRAL = 'neutral';
    case VENDOR_SELLING = 'vendor_selling';
    case VENDOR_BUYING = 'vendor_buying';

    public function label(): string
    {
        return match ($this) {
            self::NEUTRAL => 'Neutral',
            self::VENDOR_SELLING => 'Vendor Selling',
            self::VENDOR_BUYING => 'Vendor Buying',
        };
    }
}
