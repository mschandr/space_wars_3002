<?php

namespace App\Enums\Exploration;

/**
 * Knowledge Level Enum
 *
 * Represents how much a player knows about a star system.
 * Knowledge is accumulated through travel, charts, sensors, and rumors.
 * Once gained, knowledge never drops below DETECTED (fog-of-war floor).
 */
enum KnowledgeLevel: int
{
    case UNKNOWN = 0;  // No knowledge — system not yet discovered
    case DETECTED = 1;  // Coordinates only — sensor blip or warp lane endpoint
    case BASIC = 2;  // Name, star type, inhabited status, planet count
    case SURVEYED = 3;  // Full services (inhabited) or star + planets (uninhabited)
    case VISITED = 4;  // Player has been here — permanent, never decays

    /**
     * Get the human-readable label for this knowledge level.
     */
    public function label(): string
    {
        return match ($this) {
            self::UNKNOWN => 'Unknown',
            self::DETECTED => 'Detected',
            self::BASIC => 'Basic',
            self::SURVEYED => 'Surveyed',
            self::VISITED => 'Visited',
        };
    }

    /**
     * Get description of what this level reveals.
     */
    public function description(): string
    {
        return match ($this) {
            self::UNKNOWN => 'No data available',
            self::DETECTED => 'Coordinates and existence confirmed',
            self::BASIC => 'Name, star type, inhabited status, planet count',
            self::SURVEYED => 'Full service details for inhabited systems; star and planet data for uninhabited',
            self::VISITED => 'Complete firsthand knowledge — permanent',
        };
    }

    /**
     * Whether this level is permanent (never decays).
     */
    public function isPermanent(): bool
    {
        return $this === self::VISITED;
    }

    /**
     * Whether this level represents actual knowledge (not unknown).
     */
    public function isKnown(): bool
    {
        return $this !== self::UNKNOWN;
    }

    /**
     * Get opacity for UI display (0.0 - 1.0).
     */
    public function opacity(): float
    {
        return match ($this) {
            self::UNKNOWN => 0.0,
            self::DETECTED => 0.3,
            self::BASIC => 0.5,
            self::SURVEYED => 0.8,
            self::VISITED => 1.0,
        };
    }
}
