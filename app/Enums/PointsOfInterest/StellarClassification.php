<?php

namespace App\Enums\PointsOfInterest;

/**
 * Stellar Classification based on spectral types.
 * Uses the Morgan-Keenan (MK) classification system.
 */
enum StellarClassification: string
{
    case O = 'O';  // Blue supergiants
    case B = 'B';  // Blue-white giants
    case A = 'A';  // White stars
    case F = 'F';  // Yellow-white stars
    case G = 'G';  // Yellow stars (like our Sun)
    case K = 'K';  // Orange dwarfs
    case M = 'M';  // Red dwarfs

    /**
     * Human-readable label for the stellar class
     */
    public function label(): string
    {
        return match ($this) {
            self::O => 'O-Type (Blue Supergiant)',
            self::B => 'B-Type (Blue-White Giant)',
            self::A => 'A-Type (White Star)',
            self::F => 'F-Type (Yellow-White Star)',
            self::G => 'G-Type (Yellow Star)',
            self::K => 'K-Type (Orange Dwarf)',
            self::M => 'M-Type (Red Dwarf)',
        };
    }

    /**
     * Temperature range in Kelvin for this stellar class
     *
     * @return array{0: int, 1: int} [min, max]
     */
    public function temperatureRange(): array
    {
        return match ($this) {
            self::O => [30000, 60000],
            self::B => [10000, 30000],
            self::A => [7500, 10000],
            self::F => [6000, 7500],
            self::G => [5200, 6000],
            self::K => [3700, 5200],
            self::M => [2400, 3700],
        };
    }

    /**
     * Typical number of planets for this stellar class
     *
     * @return array{0: int, 1: int} [min, max]
     */
    public function planetCountRange(): array
    {
        return match ($this) {
            self::O => [0, 2],    // Short-lived, unstable, few planets
            self::B => [0, 3],    // Hot, large, few planets survive
            self::A => [1, 5],    // Moderate
            self::F => [2, 8],    // Good for planet formation
            self::G => [3, 10],   // Like our solar system
            self::K => [2, 7],    // Stable, long-lived
            self::M => [1, 6],    // Many have close-in planets
        };
    }

    /**
     * Probability of a Hot Jupiter (gas giant in close orbit)
     *
     * @return float 0.0 to 1.0
     */
    public function hotJupiterChance(): float
    {
        return match ($this) {
            self::O => 0.01,  // Very rare
            self::B => 0.02,
            self::A => 0.05,
            self::F => 0.08,
            self::G => 0.10,  // Most common
            self::K => 0.07,
            self::M => 0.03,  // Rare due to close habitable zone
        };
    }

    /**
     * Probability of an asteroid belt in the system
     *
     * @return float 0.0 to 1.0
     */
    public function asteroidBeltChance(): float
    {
        return match ($this) {
            self::O => 0.10,
            self::B => 0.15,
            self::A => 0.25,
            self::F => 0.35,
            self::G => 0.40,  // Like our solar system
            self::K => 0.30,
            self::M => 0.20,
        };
    }

    /**
     * Relative frequency of this stellar class in the galaxy.
     * Based on astronomical observations.
     *
     * @return int Weight for weighted random selection
     */
    public function weight(): int
    {
        return match ($this) {
            self::O => 1,      // 0.00003% of stars (ultra rare)
            self::B => 3,      // 0.13% of stars (very rare)
            self::A => 6,      // 0.6% of stars (rare)
            self::F => 30,     // 3% of stars (uncommon)
            self::G => 76,     // 7.6% of stars (common)
            self::K => 121,    // 12.1% of stars (very common)
            self::M => 763,    // 76.3% of stars (most common!)
        };
    }

    /**
     * Get base orbital distance for first planet (in AU)
     * Used for calculating planetary orbital distances
     */
    public function baseOrbitalDistance(): float
    {
        return match ($this) {
            self::M => 0.1,  // Closer for red dwarfs (small habitable zone)
            self::K => 0.2,
            self::G => 0.3,  // Like solar system (Mercury at ~0.4 AU)
            self::F, self::A => 0.4,
            self::B, self::O => 0.5,  // Wider orbits for massive stars
        };
    }

    /**
     * Get all stellar classifications as an array
     *
     * @return array<StellarClassification>
     */
    public static function all(): array
    {
        return self::cases();
    }
}
