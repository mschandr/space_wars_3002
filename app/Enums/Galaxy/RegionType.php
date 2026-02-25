<?php

namespace App\Enums\Galaxy;

/**
 * Region types for Points of Interest within a tiered galaxy.
 *
 * Core: Civilized center — 81% inhabited, 90% charted, active warp gates.
 * Outer: Frontier wilderness — 2% inhabited, 20% charted, rich minerals, dormant gates.
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
            self::CORE => config('game_config.tiered_galaxy.core_inhabited_percentage', 0.81),
            self::OUTER => config('game_config.tiered_galaxy.outer_inhabited_percentage', 0.02),
        };
    }

    /**
     * Get the charted percentage for this region.
     */
    public function getChartedPercentage(): float
    {
        return match ($this) {
            self::CORE => config('game_config.tiered_galaxy.core_charted_percentage', 0.90),
            self::OUTER => config('game_config.tiered_galaxy.outer_charted_percentage', 0.20),
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
     * Check if trading posts can spawn in this region.
     * Now driven by is_inhabited flag on individual stars.
     */
    public function hasTradingPosts(): bool
    {
        return true;
    }

    /**
     * Check if defenses can be deployed in this region.
     * Now driven by is_inhabited flag on individual stars.
     */
    public function hasDefenses(): bool
    {
        return true;
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
            self::OUTER => 'Frontier with rich minerals, dormant gates, and sparse civilization.',
        };
    }
}
