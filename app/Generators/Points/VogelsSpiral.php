<?php

namespace App\Generators\Points;

use App\Contracts\PointGeneratorInterface;
use App\Models\Galaxy;
use App\Models\PointOfInterest;

/**
 * Vogel's Spiral Generator
 *
 * Creates a beautiful sunflower-like spiral pattern using the golden angle (137.5°).
 * This produces natural-looking spiral galaxies with denser centers and sparser edges.
 *
 * Perfect for creating visually stunning spiral galaxy formations!
 */
final class VogelsSpiral extends AbstractPointGenerator implements PointGeneratorInterface
{
    /**
     * Golden angle in radians (137.5° ≈ 2.39996323 rad)
     * This is the angle that produces optimal packing in nature (sunflower seeds, pinecones, etc.)
     */
    private const GOLDEN_ANGLE = 2.39996322972865332;

    /**
     * @return array<int,array{0:int,1:int}>
     */
    public function sample(Galaxy $galaxy): array
    {
        $pts = [];

        // Center of the galaxy
        $centerX = $this->width / 2.0;
        $centerY = $this->height / 2.0;

        // Calculate scaling factor to fit points within bounds
        // We want the outermost points to reach near the edge
        $maxRadius = min($centerX, $centerY) * 0.95; // 95% of available radius
        $radiusScale = $maxRadius / sqrt($this->count);

        // Optional rotation offset from config (in degrees)
        $rotationOffset = (float) (config('game_config.generator_options.vogel_rotation') ?? 0);
        $rotationRad = deg2rad($rotationOffset);

        for ($i = 0; $i < $this->count; $i++) {
            // Vogel's formula
            $angle = $i * self::GOLDEN_ANGLE + $rotationRad;
            $radius = $radiusScale * sqrt($i);

            // Convert polar to Cartesian
            $x = $centerX + $radius * cos($angle);
            $y = $centerY + $radius * sin($angle);

            // Round to integers and clamp to bounds
            $x = max(0, min($this->width - 1, (int) round($x)));
            $y = max(0, min($this->height - 1, (int) round($y)));

            $pts[] = [$x, $y];
        }

        // Ensure uniqueness (rare collisions at integer rounding)
        $pts = $this->unique($pts);

        // If we lost points due to collisions, fill in the gaps
        while (count($pts) < $this->count) {
            $i = count($pts);
            $angle = $i * self::GOLDEN_ANGLE + $rotationRad;
            $radius = $radiusScale * sqrt($i);

            $x = $centerX + $radius * cos($angle);
            $y = $centerY + $radius * sin($angle);

            $x = max(0, min($this->width - 1, (int) round($x)));
            $y = max(0, min($this->height - 1, (int) round($y)));

            $key = $x.':'.$y;
            if (! in_array([$x, $y], $pts, true)) {
                $pts[] = [$x, $y];
            }
        }

        if (config('game_config.feature.persist_data')) {
            PointOfInterest::createPointsForGalaxy($galaxy, $pts);

            // Generate star systems (planets, moons, asteroids)
            $this->generateStarSystems($galaxy);
        }

        return $pts;
    }
}
