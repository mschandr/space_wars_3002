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
     * Return the proportion of systems in the region that are inhabited.
     *
     * @return float Proportion between 0.0 and 1.0 (e.g. 1.0 for fully inhabited, 0.0 for uninhabited).
     */
    public function getInhabitedPercentage(): float
    {
        return match ($this) {
            self::CORE => 1.0,   // 100% inhabited
            self::OUTER => 0.0,  // 0% inhabited (frontier)
        };
    }

    /**
     * Provide the mineral richness multiplier for the region.
     *
     * @return float The multiplier applied to base mineral deposits; `1.0` for standard richness (Core), `2.0` for double richness (Outer).
     */
    public function getMineralMultiplier(): float
    {
        return match ($this) {
            self::CORE => 1.0,   // Standard mineral deposits
            self::OUTER => 2.0,  // 2x richness in outer regions
        };
    }

    /**
     * Determine whether this region contains trading posts.
     *
     * @return bool `true` if the region contains trading posts, `false` otherwise.
     */
    public function hasTradingPosts(): bool
    {
        return match ($this) {
            self::CORE => true,
            self::OUTER => false,
        };
    }

    /**
     * Determines whether the region type includes defensive installations.
     *
     * @return bool `true` if the region includes defenses, `false` otherwise.
     */
    public function hasDefenses(): bool
    {
        return match ($this) {
            self::CORE => true,
            self::OUTER => false,
        };
    }

    /**
     * Get the default warp gate status for this region.
     *
     * @return string `'active'` for core regions, `'dormant'` for outer regions.
     */
    public function getDefaultGateStatus(): string
    {
        return match ($this) {
            self::CORE => 'active',
            self::OUTER => 'dormant',
        };
    }

    /**
     * Get a human-readable label for the region type.
     *
     * @return string The human-readable label for the enum case.
     */
    public function label(): string
    {
        return match ($this) {
            self::CORE => 'Core Region (Civilized)',
            self::OUTER => 'Outer Region (Frontier)',
        };
    }

    /**
     * Human-readable description of the region type.
     *
     * @return string A human-readable description for this region type.
     */
    public function description(): string
    {
        return match ($this) {
            self::CORE => 'Civilized systems with trading posts, defenses, and active warp gates.',
            self::OUTER => 'Untamed frontier with rich minerals, dormant gates, and no civilization.',
        };
    }
}