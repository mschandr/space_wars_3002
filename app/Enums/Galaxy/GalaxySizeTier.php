<?php

namespace App\Enums\Galaxy;

/**
 * Galaxy size tiers define the overall dimensions and star counts.
 *
 * | Size   | Outer Bounds | Core Bounds | Core Stars | Outer Stars | Total |
 * |--------|-------------|-------------|------------|-------------|-------|
 * | Small  | 500×500     | 250×250     | 100        | 150         | 250   |
 * | Medium | 1500×1500   | 750×750     | 300        | 450         | 750   |
 * | Large  | 2500×2500   | 1250×1250   | 500        | 750         | 1250  |
 *
 * Formulas:
 * - Core bounds = outer_size / 2
 * - Core stars = outer_size / 5
 * - Outer stars = (outer_size / 2) - core_stars
 */
enum GalaxySizeTier: string
{
    case SMALL = 'small';
    case MEDIUM = 'medium';
    case LARGE = 'large';

    /**
     * Get the outer bounds (width/height) for this tier.
     */
    public function getOuterBounds(): int
    {
        return match ($this) {
            self::SMALL => 500,
            self::MEDIUM => 1500,
            self::LARGE => 2500,
        };
    }

    /**
     * Get the core bounds (width/height) for this tier.
     * Formula: outer_size / 2
     */
    public function getCoreBounds(): int
    {
        return (int) ($this->getOuterBounds() / 2);
    }

    /**
     * Get the number of stars in the core region.
     * Formula: outer_size / 5
     */
    public function getCoreStars(): int
    {
        return (int) ($this->getOuterBounds() / 5);
    }

    /**
     * Get the number of stars in the outer (frontier) region.
     * Formula: (outer_size / 2) - core_stars
     */
    public function getOuterStars(): int
    {
        return (int) (($this->getOuterBounds() / 2) - $this->getCoreStars());
    }

    /**
     * Get total star count for this tier.
     */
    public function getTotalStars(): int
    {
        return $this->getCoreStars() + $this->getOuterStars();
    }

    /**
     * Get the core region bounds as an array.
     * The core is centered in the galaxy.
     */
    public function getCoreBoundsArray(): array
    {
        $outerSize = $this->getOuterBounds();
        $coreSize = $this->getCoreBounds();
        $offset = ($outerSize - $coreSize) / 2;

        return [
            'x_min' => (int) $offset,
            'x_max' => (int) ($offset + $coreSize),
            'y_min' => (int) $offset,
            'y_max' => (int) ($offset + $coreSize),
        ];
    }

    /**
     * Get recommended grid size for sectors.
     */
    public function getRecommendedGridSize(): int
    {
        return match ($this) {
            self::SMALL => 10,
            self::MEDIUM => 15,
            self::LARGE => 20,
        };
    }

    /**
     * Get warp gate adjacency threshold for this tier.
     * Formula: max dimension / 15
     */
    public function getWarpGateAdjacency(): int
    {
        return (int) ($this->getOuterBounds() / 15);
    }

    /**
     * Get human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::SMALL => 'Small Galaxy (500×500)',
            self::MEDIUM => 'Medium Galaxy (1500×1500)',
            self::LARGE => 'Large Galaxy (2500×2500)',
        };
    }

    /**
     * Get all tiers as options array for API responses.
     * Returns static pre-computed values for performance.
     */
    public static function toOptionsArray(): array
    {
        return [
            [
                'value' => 'small',
                'label' => 'Small Galaxy (500×500)',
                'outer_bounds' => 500,
                'core_bounds' => 250,
                'core_stars' => 100,
                'outer_stars' => 150,
                'total_stars' => 250,
            ],
            [
                'value' => 'medium',
                'label' => 'Medium Galaxy (1500×1500)',
                'outer_bounds' => 1500,
                'core_bounds' => 750,
                'core_stars' => 300,
                'outer_stars' => 450,
                'total_stars' => 750,
            ],
            [
                'value' => 'large',
                'label' => 'Large Galaxy (2500×2500)',
                'outer_bounds' => 2500,
                'core_bounds' => 1250,
                'core_stars' => 500,
                'outer_stars' => 750,
                'total_stars' => 1250,
            ],
        ];
    }
}
