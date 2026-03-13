<?php

namespace App\Enums\Vendor;

enum InteractionBucket: string
{
    case FIRST_VISIT = 'first_visit';
    case SECOND_VISIT = 'second_visit';
    case THIRD_VISIT = 'third_visit';
    case REPEAT_CUSTOMER = 'repeat_customer';

    public function label(): string
    {
        return match ($this) {
            self::FIRST_VISIT => 'First Visit',
            self::SECOND_VISIT => 'Second Visit',
            self::THIRD_VISIT => 'Third Visit',
            self::REPEAT_CUSTOMER => 'Repeat Customer',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::FIRST_VISIT => 'Player\'s first time meeting this vendor',
            self::SECOND_VISIT => 'Player\'s second time meeting this vendor',
            self::THIRD_VISIT => 'Player\'s third time meeting this vendor',
            self::REPEAT_CUSTOMER => 'Player is an established repeat customer',
        };
    }

    /**
     * Get interaction count threshold for this bucket.
     * Used to determine which bucket a player falls into.
     */
    public function minInteractionCount(): int
    {
        return match ($this) {
            self::FIRST_VISIT => 0,
            self::SECOND_VISIT => 1,
            self::THIRD_VISIT => 2,
            self::REPEAT_CUSTOMER => 3,
        };
    }
}
