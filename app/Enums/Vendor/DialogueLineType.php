<?php

namespace App\Enums\Vendor;

enum DialogueLineType: string
{
    case GREETING = 'greeting';
    case INVENTORY_PITCH = 'inventory_pitch';
    case DEAL_ACCEPTED = 'deal_accepted';
    case DEAL_REJECTED = 'deal_rejected';
    case FAREWELL = 'farewell';

    public function label(): string
    {
        return match ($this) {
            self::GREETING => 'Greeting',
            self::INVENTORY_PITCH => 'Inventory Pitch',
            self::DEAL_ACCEPTED => 'Deal Accepted',
            self::DEAL_REJECTED => 'Deal Rejected',
            self::FAREWELL => 'Farewell',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::GREETING => 'Initial greeting when vendor meets player',
            self::INVENTORY_PITCH => 'Vendor promoting specific inventory items',
            self::DEAL_ACCEPTED => 'Vendor response to accepted trade',
            self::DEAL_REJECTED => 'Vendor response to rejected offer',
            self::FAREWELL => 'Vendor goodbye when player leaves',
        };
    }
}
