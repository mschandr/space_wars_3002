<?php

namespace App\Generators\Points;

use App\Contracts\PointGeneratorInterface;
use App\Models\Galaxy;
use App\Models\PointOfInterest;

/**
 * Stratified Grid Generator
 *
 * Divides the galaxy into equal grid cells and places one point randomly within each cell.
 * This ensures excellent coverage with no large empty regions while maintaining randomness.
 *
 * Perfect for balanced gameplay where resources should be evenly distributed!
 */
final class StratifiedGrid extends AbstractPointGenerator implements PointGeneratorInterface
{
    /**
     * @return array<int,array{0:int,1:int}>
     */
    public function sample(Galaxy $galaxy): array
    {
        $pts = [];

        // Calculate grid dimensions - aim for roughly square cells
        $cellsPerRow = (int) ceil(sqrt($this->count));
        $cellsPerCol = (int) ceil($this->count / $cellsPerRow);

        // Cell dimensions
        $cellWidth = $this->width / $cellsPerRow;
        $cellHeight = $this->height / $cellsPerCol;

        $cellIndex = 0;

        // Iterate through grid cells
        for ($row = 0; $row < $cellsPerCol && $cellIndex < $this->count; $row++) {
            for ($col = 0; $col < $cellsPerRow && $cellIndex < $this->count; $col++) {
                // Calculate cell boundaries
                $minX = (int) floor($col * $cellWidth);
                $maxX = (int) floor(($col + 1) * $cellWidth) - 1;
                $minY = (int) floor($row * $cellHeight);
                $maxY = (int) floor(($row + 1) * $cellHeight) - 1;

                // Clamp to galaxy bounds
                $minX = max(0, $minX);
                $maxX = min($this->width - 1, $maxX);
                $minY = max(0, $minY);
                $maxY = min($this->height - 1, $maxY);

                // Place one random point within this cell
                $x = $this->randomizer->getInt($minX, $maxX);
                $y = $this->randomizer->getInt($minY, $maxY);

                $pts[] = [$x, $y];
                $cellIndex++;
            }
        }

        // Ensure uniqueness (very rare collisions at cell boundaries)
        $pts = $this->unique($pts);

        // If we lost points to collisions, add more in random cells
        while (count($pts) < $this->count) {
            $col = $this->randomizer->getInt(0, $cellsPerRow - 1);
            $row = $this->randomizer->getInt(0, $cellsPerCol - 1);

            $minX = (int) floor($col * $cellWidth);
            $maxX = (int) floor(($col + 1) * $cellWidth) - 1;
            $minY = (int) floor($row * $cellHeight);
            $maxY = (int) floor(($row + 1) * $cellHeight) - 1;

            $minX = max(0, $minX);
            $maxX = min($this->width - 1, $maxX);
            $minY = max(0, $minY);
            $maxY = min($this->height - 1, $maxY);

            $x = $this->randomizer->getInt($minX, $maxX);
            $y = $this->randomizer->getInt($minY, $maxY);

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
