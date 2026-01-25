<?php

namespace App\Enums\Galaxy;

/**
 * Region types for Points of Interest within a tiered galaxy.
 *
 * Core: Civilized center with trading posts, defenses, and 100% inhabited systems.
 * Outer: Frontier wilderness with rich minerals, 0% inhabited, and dormant gates.
 */
enum RegionType: string
{
    case CORE = 'core';
    case OUTER = 'outer';

    /**
     * Get the inhabited percentage for this region.
     */
    public function getInhabitedPercentage(): float
    {
        return match ($this) {
            self::CORE => 1.0,   // 100% inhabited
            self::OUTER => 0.0,  // 0% inhabited (frontier)
        };
    }

    /**
     * Get the mineral richness multiplier for this region.
     */
    public function getMineralMultiplier(): float
    {
        return match ($this) {
            self::CORE => 1.0,   // Standard mineral deposits
            self::OUTER => 2.0,  // 2x richness in outer regions
        };
    }

    /**
     * Check if trading posts should spawn in this region.
     */
    public function hasTradingPosts(): bool
    {
        return match ($this) {
            self::CORE => true,
            self::OUTER => false,
        };
    }

    /**
     * Check if defenses should be deployed in this region.
     */
    public function hasDefenses(): bool
    {
        return match ($this) {
            self::CORE => true,
            self::OUTER => false,
        };
    }

    /**
     * Get warp gate status for this region.
     */
    public function getDefaultGateStatus(): string
    {
        return match ($this) {
            self::CORE => 'active',
            self::OUTER => 'dormant',
        };
    }

    /**
     * Get human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::CORE => 'Core Region (Civilized)',
            self::OUTER => 'Outer Region (Frontier)',
        };
    }

    /**
     * Get description for this region type.
     */
    public function description(): string
    {
        return match ($this) {
            self::CORE => 'Civilized systems with trading posts, defenses, and active warp gates.',
            self::OUTER => 'Untamed frontier with rich minerals, dormant gates, and no civilization.',
        };
    }
}
