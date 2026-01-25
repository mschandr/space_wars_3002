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
     * Return the outer square bounds (width and height) associated with this galaxy size tier.
     *
     * @return int The outer bounds length in units (e.g., 500 for small, 1500 for medium, 2500 for large).
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
     * Get the core bounds size for this tier.
     *
     * @return int Core bounds width/height (half of the outer bounds).
     */
    public function getCoreBounds(): int
    {
        return (int) ($this->getOuterBounds() / 2);
    }

    /**
     * The number of stars allocated to the core region for this size tier.
     *
     * @return int The core-region star count for the tier.
     */
    public function getCoreStars(): int
    {
        return (int) ($this->getOuterBounds() / 5);
    }

    /**
     * Determine the count of stars located in the outer (frontier) region of the galaxy tier.
     *
     * @return int The number of outer-region stars.
     */
    public function getOuterStars(): int
    {
        return (int) (($this->getOuterBounds() / 2) - $this->getCoreStars());
    }

    /**
         * Compute the total number of stars in both the core and outer regions for this tier.
         *
         * @return int The total number of stars for the tier.
         */
    public function getTotalStars(): int
    {
        return $this->getCoreStars() + $this->getOuterStars();
    }

    /**
     * Calculate the core region bounds centered within the outer galaxy and return them as integer coordinates.
     *
     * @return array{ x_min: int, x_max: int, y_min: int, y_max: int } Associative array containing the core region bounds:
     *  - `x_min`: left X coordinate
     *  - `x_max`: right X coordinate
     *  - `y_min`: top Y coordinate
     *  - `y_max`: bottom Y coordinate
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
     * Returns the recommended grid size for dividing the galaxy into sectors for this tier.
     *
     * @return int The recommended grid size (number of units per sector) for the tier.
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
     * Compute the warp gate adjacency threshold for the tier.
     *
     * Calculated as the outer bounds divided by 15.
     *
     * @return int The adjacency threshold (outer bounds / 15).
     */
    public function getWarpGateAdjacency(): int
    {
        return (int) ($this->getOuterBounds() / 15);
    }

    /**
     * Human-readable label for the galaxy size tier.
     *
     * @return string The label describing the tier and its outer dimensions (e.g., "Small Galaxy (500×500)").
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
     * Provide a static, pre-computed list of galaxy size tier options for API responses.
     *
     * Each array entry represents a tier and contains:
     * - `value`: tier identifier (`small`, `medium`, `large`)
     * - `label`: human-readable label including dimensions
     * - `outer_bounds`: outer dimension (width and height)
     * - `core_bounds`: core region dimension
     * - `core_stars`: number of core stars
     * - `outer_stars`: number of outer/frontier stars
     * - `total_stars`: total star count (core + outer)
     *
     * @return array<int,array<string,mixed>> List of tier option arrays for small, medium, and large.
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