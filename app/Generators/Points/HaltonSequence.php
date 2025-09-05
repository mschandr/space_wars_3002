<?php

namespace App\Generators\Points;

use App\Contracts\PointGeneratorInterface;

final class HaltonSequence extends AbstractPointGenerator implements PointGeneratorInterface
{
    /**
     * @return array<int,array{0:int,1:int}>
     */
    public function sample(): array
    {
        $pts = [];
        $i   = 1;

        while (count($pts) < $this->count) {
            $x = (int)floor($this->halton($i, 2) * $this->width);
            $y = (int)floor($this->halton($i, 3) * $this->height);

            // clamp to bounds
            $x = max(0, min($this->width - 1, $x));
            $y = max(0, min($this->height - 1, $y));

            if ($this->isFarEnough([$x, $y], $pts)) {
                $pts[] = [$x, $y];
            }

            $i++;
        }

        return $pts;
    }

    /**
     * Halton sequence radical inverse.
     */
    private function halton(int $index, int $base): float
    {
        $result = 0.0;
        $f = 1.0 / $base;

        while ($index > 0) {
            $result += $f * ($index % $base);
            $index = intdiv($index, $base);
            $f /= $base;
        }

        return $result;
    }
}
