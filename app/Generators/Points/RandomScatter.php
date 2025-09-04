<?php

namespace App\Generators\Points;

use App\Contracts\PointGeneratorInterface;

/**
 * Random scatter, uses true pseudo randomness to generate ~N points of interest but ensures
 * that it respects the fact that it is at least spacingFactor away from any other point.
 */
final class RandomScatter extends AbstractPointGenerator implements PointGeneratorInterface
{
    /**
     * @return array<int,array{0:int,1:int}>
     */
    public function sample(): array
    {
        $pts = [];

        while (count($pts) < $this->count) {
            $x = $this->randomizer
                ? $this->randomizer->getInt(0, $this->width - 1)
                : mt_rand(0, $this->width - 1);

            $y = $this->randomizer
                ? $this->randomizer->getInt(0, $this->height - 1)
                : mt_rand(0, $this->height - 1);

            if ($this->isFarEnough([$x, $y], $pts)) {
                $pts[] = [$x, $y];
            }
        }

        return $pts;
    }
}
