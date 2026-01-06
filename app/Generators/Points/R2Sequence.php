<?php

namespace App\Generators\Points;

use App\Contracts\PointGeneratorInterface;
use App\Models\Galaxy;
use App\Models\PointOfInterest;

/**
 * R2 Low-Discrepancy Sequence Generator
 *
 * Uses the plastic constant (φ ≈ 1.32471...) to generate well-distributed points.
 * The R2 sequence has better 2D properties than Halton, with less directional bias.
 *
 * The plastic constant is the real solution to x³ = x + 1, and creates beautiful
 * quasi-random patterns with excellent coverage properties.
 */
final class R2Sequence extends AbstractPointGenerator implements PointGeneratorInterface
{
    /**
     * Plastic constant φ (Phi) - real solution to x³ = x + 1
     * φ ≈ 1.32471795724474602596...
     */
    private const PLASTIC_CONSTANT = 1.32471795724474602596;

    /**
     * α₁ = 1/φ ≈ 0.754877666246692760049...
     */
    private const ALPHA_1 = 0.7548776662466927;

    /**
     * α₂ = 1/φ² ≈ 0.569840290998053265911...
     */
    private const ALPHA_2 = 0.5698402909980532;

    /**
     * @return array<int,array{0:int,1:int}>
     */
    public function sample(Galaxy $galaxy): array
    {
        $pts = [];
        $i = 0;

        while (count($pts) < $this->count) {
            // R2 sequence formula
            $xFrac = fmod(0.5 + ($i * self::ALPHA_1), 1.0);
            $yFrac = fmod(0.5 + ($i * self::ALPHA_2), 1.0);

            // Map from [0,1] to galaxy dimensions
            $x = (int) floor($xFrac * $this->width);
            $y = (int) floor($yFrac * $this->height);

            // Clamp to bounds
            $x = max(0, min($this->width - 1, $x));
            $y = max(0, min($this->height - 1, $y));

            // Check spacing constraint if configured
            if ($this->spacingFactor > 0 && count($pts) > 0) {
                if ($this->isFarEnough([$x, $y], $pts)) {
                    $pts[] = [$x, $y];
                }
            } else {
                // No spacing constraint - just ensure uniqueness
                $key = $x . ':' . $y;
                $found = false;
                foreach ($pts as [$px, $py]) {
                    if ($px === $x && $py === $y) {
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $pts[] = [$x, $y];
                }
            }

            $i++;

            // Safety check to prevent infinite loops
            if ($i > $this->count * 1000) {
                break;
            }
        }

        // Ensure we have exactly the requested count
        $pts = array_slice($pts, 0, $this->count);

        if (config('game_config.feature.persist_data')) {
            PointOfInterest::createPointsForGalaxy($galaxy, $pts);

            // Generate star systems (planets, moons, asteroids)
            $this->generateStarSystems($galaxy);
        }

        return $pts;
    }
}
