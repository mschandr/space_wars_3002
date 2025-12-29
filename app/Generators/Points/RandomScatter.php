<?php

namespace App\Generators\Points;

use App\Contracts\PointGeneratorInterface;
use App\Models\Galaxy;
use App\Models\PointOfInterest;
use Assert\AssertionFailedException;

/**
 * Random scatter, uses true pseudo randomness to generate ~N points of interest but ensures
 * that it respects the fact that it is at least spacingFactor away from any other point.
 */
final class RandomScatter extends AbstractPointGenerator implements PointGeneratorInterface
{
    /**
     * @return array<int,array{0:int,1:int}>
     *
     * @throws AssertionFailedException
     */
    public function sample(Galaxy $galaxy): array
    {
        $points = [];
        while (count($points) < $this->count) {
            $attempts = 0;
            do {
                $x = $this->randomizer
                    ? $this->randomizer->getInt(0, $this->width - 1)
                    : mt_rand(0, $this->width - 1);
                $y = $this->randomizer
                    ? $this->randomizer->getInt(0, $this->height - 1)
                    : mt_rand(0, $this->height - 1);
                $key = $x.':'.$y;
                $attempts++;
            } while (isset($points[$key]) && $attempts < 3);

            // At this point: either unique, or forced duplicate after 3 tries
            $points[$key] = [$x, $y];
        }
        if (config('game_config.feature.persist_data')) {
            PointOfInterest::createPointsForGalaxy($galaxy, array_values($points));

            // Generate star systems (planets, moons, asteroids)
            $this->generateStarSystems($galaxy);
        }

        return array_values($points);
    }
}
