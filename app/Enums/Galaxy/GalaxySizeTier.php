<?php

namespace App\Enums\Galaxy;

/**
 * Galaxy size tiers define the overall dimensions and star counts.
 *
 * | Size    | Outer Bounds | Core Bounds | Core Stars | Outer Stars | Total |
 * |---------|--------------|-------------|------------|-------------|-------|
 * | Small   | 500×500      | 250×250     | 100        | 150         | 250   |
 * | Medium  | 1500×1500    | 750×750     | 300        | 450         | 750   |
 * | Large   | 2500×2500    | 1250×1250   | 500        | 750         | 1250  |
 * | Massive | 5000×5000    | 2500×2500   | 1000       | 1500        | 2500  | <- purely for testing
 *
 * Massive tier uses two-phase generation:
 * - Phase 1: Core region (2500×2500 centered) with 1000 civilized stars
 * - Phase 2: Outer frontier (5000×5000) with 1500 colony worlds
 */
enum GalaxySizeTier: string
{
    case SMALL = 'small';
    case MEDIUM = 'medium';
    case LARGE = 'large';
    case MASSIVE = 'massive';

    /**
     * Get all tiers including secret ones (for admin/internal use).
     */
    public static function toFullOptionsArray(): array
    {
        $options = self::toOptionsArray();
        $options[] = [
            'value' => 'massive',
            'label' => 'Massive Galaxy (5000×5000)',
            'outer_bounds' => 5000,
            'core_bounds' => 2500,
            'core_stars' => 1000,
            'outer_stars' => 1500,
            'total_stars' => 2500,
            'secret' => true,
        ];

        return $options;
    }

    /**
     * Get all public tiers as options array for API responses.
     * Returns static pre-computed values for performance.
     * Note: Secret tiers (MASSIVE) are excluded from public listing.
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

    /**
     * Get total star count for this tier.
     */
    public function getTotalStars(): int
    {
        return $this->getCoreStars() + $this->getOuterStars();
    }

    /**
     * Get the number of stars in the core region.
     */
    public function getCoreStars(): int
    {
        return match ($this) {
            self::SMALL => 100,
            self::MEDIUM => 300,
            self::LARGE => 500,
            self::MASSIVE => 1000,
        };
    }

    /**
     * Get the number of stars in the outer (frontier) region.
     */
    public function getOuterStars(): int
    {
        return match ($this) {
            self::SMALL => 150,
            self::MEDIUM => 450,
            self::LARGE => 750,
            self::MASSIVE => 1500,
        };
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
     * Get the outer bounds (width/height) for this tier.
     */
    public function getOuterBounds(): int
    {
        return match ($this) {
            self::SMALL => 500,
            self::MEDIUM => 1500,
            self::LARGE => 2500,
            self::MASSIVE => 5000,
        };
    }

    /**
     * Get the core bounds (width/height) for this tier.
     */
    public function getCoreBounds(): int
    {
        return match ($this) {
            self::SMALL => 250,
            self::MEDIUM => 750,
            self::LARGE => 1250,
            self::MASSIVE => 2500,  // 2500x2500 core centered in 5000x5000 galaxy
        };
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
            self::MASSIVE => 25,
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
            self::MASSIVE => 'Massive Galaxy (5000×5000)',
        };
    }

    /**
     * Check if this tier is hidden from public selection.
     */
    public function isSecret(): bool
    {
        return $this === self::MASSIVE;
    }
}
