<?php

namespace App\Generators\Points;

use App\Contracts\PointGeneratorInterface;
use App\Models\Galaxy;
use Assert\AssertionFailedException;

/**
 * Poisson-disk sample (Bridson-style)
 *
 * This class produces ~N points of interest over an area (defined by XY or Height, Width) each
 * point of interest will be at least a distance of r which is a tunable number
 */
final class PoissonDisk extends AbstractPointGenerator implements PointGeneratorInterface
{
    /**
     * @return array<int,array{0:float,1:float}> | array{points:array<int,array{0:int,1:int}>,r:float}
     *
     * @throws AssertionFailedException
     */
    public function sample(Galaxy $galaxy): array
    {
        $r = $this->radius($this->spacingFactor);
        $cell = $r / sqrt(2.0);
        $gw = max(1, (int) ceil($this->width / $cell));
        $gh = max(1, (int) ceil($this->height / $cell));
        $grid = array_fill(0, $gw * $gh, -1);

        [$attempts, $margin, $floats] = $this->extractParameters();

        $pts = [];
        $active = [];
        $addPoint = $this->createAddPointClosure($pts, $active, $grid, $cell, $gw, $gh);

        // Seed with one random point
        $addPoint($this->rf($margin, $this->width - $margin), $this->rf($margin, $this->height - $margin));

        // Bridson-ish loop
        $this->runBridsonAlgorithm($pts, $active, $grid, $r, $cell, $gw, $gh, $attempts, $margin);

        return $this->formatResults($pts, $floats, $r, $galaxy);
    }

    /**
     * Extract configuration parameters.
     */
    private function extractParameters(): array
    {
        $attempts = (int) config('game_config.generator_options.attempts') ?? $this->options['attempts'];
        $margin = (int) config('game_config.generator_options.margin') ?? $this->options['margin'];
        $floats = (bool) config('game_config.generator_options.floats') ?? $this->options['returnFloats'];

        return [$attempts, $margin, $floats];
    }

    /**
     * Create closure for adding points to grid.
     */
    private function createAddPointClosure(&$pts, &$active, &$grid, float $cell, int $gw, int $gh)
    {
        return function (float $x, float $y) use (&$pts, &$active, &$grid, $cell, $gw, $gh) {
            $idx = count($pts);
            $pts[] = [$x, $y];
            $active[] = $idx;
            $gx = (int) ($x / $cell);
            $gy = (int) ($y / $cell);
            $gx = max(0, min($gw - 1, $gx));
            $gy = max(0, min($gh - 1, $gy));
            $grid[$gy * $gw + $gx] = $idx;
        };
    }

    /**
     * Run the Bridson algorithm main loop.
     */
    private function runBridsonAlgorithm(&$pts, &$active, &$grid, float $r, float $cell, int $gw, int $gh, int $attempts, int $margin): void
    {
        while (! empty($active) && count($pts) < $this->count) {
            $ai = $this->randomizer->getInt(0, count($active) - 1);
            [$px, $py] = $pts[$active[$ai]];

            if (! $this->attemptPlacement($pts, $active, $grid, $px, $py, $r, $cell, $gw, $gh, $attempts, $margin, $ai)) {
                array_splice($active, $ai, 1);
            }
        }
    }

    /**
     * Attempt to place a point near an active point.
     */
    private function attemptPlacement(&$pts, &$active, &$grid, float $px, float $py, float $r, float $cell, int $gw, int $gh, int $attempts, int $margin, int $ai): bool
    {
        $addPoint = $this->createAddPointClosure($pts, $active, $grid, $cell, $gw, $gh);

        for ($i = 0; $i < $attempts; $i++) {
            [$x, $y] = $this->generateCandidatePoint($px, $py, $r);

            if (! $this->isValidPosition($x, $y, $margin)) {
                continue;
            }

            $gx = (int) ($x / $cell);
            $gy = (int) ($y / $cell);

            if ($this->checkDistanceConstraints($pts, $grid, $x, $y, $r, $gw, $gh, $gx, $gy)) {
                $addPoint($x, $y);
                return true;
            }
        }

        return false;
    }

    /**
     * Generate candidate point coordinates.
     */
    private function generateCandidatePoint(float $px, float $py, float $r): array
    {
        $u = $this->randomizer->nextFloat();
        $ang = 2.0 * M_PI * $this->randomizer->nextFloat();
        $rad = $r * sqrt(1.0 + (3.0 * $u));
        $x = $px + $rad * cos($ang);
        $y = $py + $rad * sin($ang);

        return [$x, $y];
    }

    /**
     * Check if position is within valid bounds.
     */
    private function isValidPosition(float $x, float $y, int $margin): bool
    {
        return ! ($x < $margin || $y < $margin || $x >= $this->width - $margin || $y >= $this->height - $margin);
    }

    /**
     * Check distance constraints against neighbors.
     */
    private function checkDistanceConstraints(&$pts, &$grid, float $x, float $y, float $r, int $gw, int $gh, int $gx, int $gy): bool
    {
        $r2 = $r * $r;

        for ($yy = max(0, $gy - 2); $yy <= min($gh - 1, $gy + 2); $yy++) {
            for ($xx = max(0, $gx - 2); $xx <= min($gw - 1, $gx + 2); $xx++) {
                $q = $grid[$yy * $gw + $xx];
                if ($q < 0) {
                    continue;
                }

                [$qx, $qy] = $pts[$q];
                $dx = $qx - $x;
                $dy = $qy - $y;

                if (($dx * $dx + $dy * $dy) < $r2) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Format and return results.
     */
    private function formatResults(array $pts, bool $floats, float $r, Galaxy $galaxy): array
    {
        if ($floats) {
            return [
                'points' => $pts,
                'r' => $r,
            ];
        }

        $snapped = array_map(fn ($p) => [(int) round($p[0]), (int) round($p[1])], $pts);
        $uniq = [];
        foreach ($snapped as $p) {
            $uniq[$p[0].','.$p[1]] = $p;
        }

        $this->persistIfEnabled($galaxy, array_values($uniq));

        return array_values($uniq);
    }

    private function radius(float $spacing_factor): float
    {
        return max(1.0, $spacing_factor); // treat spacingFactor as direct minimum spacing
    }

    private function rf(float $a, float $b): float
    {
        return $a + $this->randomizer->nextFloat() * ($b - $a);
    }
}
