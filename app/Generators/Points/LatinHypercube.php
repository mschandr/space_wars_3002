<?php

namespace App\Generators\Points;

use App\Contracts\PointGeneratorInterface;
use App\Models\Galaxy;

/**
 * Latin Hypercube Sampling Generator
 *
 * Ensures each "row" and "column" of the galaxy gets exactly one point.
 * This provides excellent statistical properties and guaranteed even coverage.
 *
 * Think of it like a Sudoku constraint - no two points share the same X or Y band.
 * Perfect for simulations and ensuring maximum spread!
 */
final class LatinHypercube extends AbstractPointGenerator implements PointGeneratorInterface
{
    /**
     * @return array<int,array{0:int,1:int}>
     */
    public function sample(Galaxy $galaxy): array
    {
        $pts = [];

        // Segment size for each dimension
        $xSegmentSize = $this->width / $this->count;
        $ySegmentSize = $this->height / $this->count;

        // Create index arrays for shuffling
        $xIndices = range(0, $this->count - 1);
        $yIndices = range(0, $this->count - 1);

        // Shuffle independently using our seeded randomizer
        $this->shuffle($xIndices);
        $this->shuffle($yIndices);

        // Generate points by pairing shuffled indices
        for ($i = 0; $i < $this->count; $i++) {
            // Calculate segment boundaries
            $xSegment = $xIndices[$i];
            $ySegment = $yIndices[$i];

            $minX = (int) floor($xSegment * $xSegmentSize);
            $maxX = (int) floor(($xSegment + 1) * $xSegmentSize) - 1;
            $minY = (int) floor($ySegment * $ySegmentSize);
            $maxY = (int) floor(($ySegment + 1) * $ySegmentSize) - 1;

            // Clamp to galaxy bounds
            $minX = max(0, $minX);
            $maxX = min($this->width - 1, $maxX);
            $minY = max(0, $minY);
            $maxY = min($this->height - 1, $maxY);

            // Ensure valid range
            if ($maxX < $minX) {
                $maxX = $minX;
            }
            if ($maxY < $minY) {
                $maxY = $minY;
            }

            // Random point within this segment
            $x = $this->randomizer->getInt($minX, $maxX);
            $y = $this->randomizer->getInt($minY, $maxY);

            $pts[] = [$x, $y];
        }

        // Latin Hypercube should guarantee uniqueness, but check anyway
        $pts = $this->unique($pts);

        // Build hash set from existing points for O(1) lookups
        $seen = [];
        foreach ($pts as [$px, $py]) {
            $seen[$px.':'.$py] = true;
        }

        // Handle rare edge case of collisions
        while (count($pts) < $this->count) {
            $i = count($pts);
            $xSegment = $i % $this->count;
            $ySegment = ($i * 7) % $this->count; // Use prime offset to avoid patterns

            $minX = (int) floor($xSegment * $xSegmentSize);
            $maxX = (int) floor(($xSegment + 1) * $xSegmentSize) - 1;
            $minY = (int) floor($ySegment * $ySegmentSize);
            $maxY = (int) floor(($ySegment + 1) * $ySegmentSize) - 1;

            $minX = max(0, min($this->width - 1, $minX));
            $maxX = max(0, min($this->width - 1, $maxX));
            $minY = max(0, min($this->height - 1, $minY));
            $maxY = max(0, min($this->height - 1, $maxY));

            // Ensure valid range
            if ($maxX < $minX) {
                $maxX = $minX;
            }
            if ($maxY < $minY) {
                $maxY = $minY;
            }

            $x = $this->randomizer->getInt($minX, $maxX);
            $y = $this->randomizer->getInt($minY, $maxY);

            $key = $x.':'.$y;
            if (! isset($seen[$key])) {
                $pts[] = [$x, $y];
                $seen[$key] = true;
            }
        }

        $this->persistIfEnabled($galaxy, $pts);

        return $pts;
    }

    /**
     * Fisher-Yates shuffle using our seeded randomizer
     *
     * @param  array<int>  $array
     */
    private function shuffle(array &$array): void
    {
        $n = count($array);
        for ($i = $n - 1; $i > 0; $i--) {
            $j = $this->randomizer->getInt(0, $i);
            // Swap
            $temp = $array[$i];
            $array[$i] = $array[$j];
            $array[$j] = $temp;
        }
    }
}
