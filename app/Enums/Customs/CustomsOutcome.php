<?php

namespace App\Enums\Customs;

enum CustomsOutcome: string
{
    case CLEARED = 'cleared';           // No issues
    case FINED = 'fined';               // Illegal items found, player pays fine
    case CARGO_SEIZED = 'cargo_seized'; // Illegal cargo confiscated
    case BRIBED = 'bribed';             // Player paid bribe to avoid fine
    case IMPOUNDED = 'impounded';       // Ship is impounded, player cannot leave

    /**
     * Get human-readable label
     */
    public function label(): string
    {
        return match ($this) {
            self::CLEARED => 'Cleared',
            self::FINED => 'Fined',
            self::CARGO_SEIZED => 'Cargo Seized',
            self::BRIBED => 'Bribed Official',
            self::IMPOUNDED => 'Ship Impounded',
        };
    }

    /**
     * Is this outcome considered a violation?
     */
    public function isViolation(): bool
    {
        return $this !== self::CLEARED && $this !== self::BRIBED;
    }

    /**
     * Did this outcome result in a successful bribe?
     */
    public function wasBribed(): bool
    {
        return $this === self::BRIBED;
    }
}
