<?php

namespace App\Generators\Points;

use App\Contracts\PointGeneratorInterface;
use App\Models\Galaxy;
use App\Models\PointOfInterest;

/**
 * Uniform Random Generator
 *
 * Truly independent random placement with no spacing constraints.
 * Each point is placed completely randomly, which naturally creates clusters and voids.
 *
 * This simulates true randomness where clustering is expected (Poisson clumping).
 * Perfect for chaotic, unpredictable galaxy layouts!
 */
final class UniformRandom extends AbstractPointGenerator implements PointGeneratorInterface
{
    /**
     * @return array<int,array{0:int,1:int}>
     */
    public function sample(Galaxy $galaxy): array
    {
        $pts = [];
        $seen = [];

        while (count($pts) < $this->count) {
            // Completely random placement
            $x = $this->randomizer->getInt(0, $this->width - 1);
            $y = $this->randomizer->getInt(0, $this->height - 1);

            // Only check for exact duplicates (no spacing constraint)
            $key = $x . ':' . $y;

            if (!isset($seen[$key])) {
                $pts[] = [$x, $y];
                $seen[$key] = true;
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
