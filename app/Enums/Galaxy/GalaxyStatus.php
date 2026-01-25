<?php

namespace App\Enums\Galaxy;

enum GalaxyStatus: int
{
    case DRAFT = 0;
    case ACTIVE = 1;
    case INACTIVE = 2;
    case ARCHIVED = 3;
    case SUSPENDED = 4;
    case PROCESSING = 5;

    /**
     * Get a human-readable label for the enum case.
     *
     * @return string The label corresponding to the current status: `Draft`, `Active`, `Inactive`, `Archived`, `Suspended`, or `Processing`.
     */
    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::ACTIVE => 'Active',
            self::INACTIVE => 'Inactive',
            self::ARCHIVED => 'Archived',
            self::SUSPENDED => 'Suspended',
            self::PROCESSING => 'Processing',
        };
    }

    public function isPlayable(): bool
    {
        return $this === self::ACTIVE;
    }
}