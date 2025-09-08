<?php

namespace App\Generators\Points;

use App\Contracts\PointGeneratorInterface;
use App\Models\Galaxy;
use App\Models\PointOfInterest;

/**
 * Poisson-disk sample (Bridson-style)
 *
 * This class produces ~N points of interest over an area (defined by XY or Height, Width) each
 * point of interest will be at least a distance of r which is a tunable number
 *
 */
final class PoissonDisk extends AbstractPointGenerator implements PointGeneratorInterface
{

    /**
     * @param Galaxy $galaxy
     * @return array<int,array{0:float,1:float}> | array{points:array<int,array{0:int,1:int}>,r:float}
     */
    public function sample(Galaxy $galaxy): array
    {
        // --- derive radius & grid (trimmed; but not hyper-optimized) ---
        $r    = $this->radius($this->spacingFactor);
        $cell = $r / sqrt(2.0);
        $gw   = max(1, (int)ceil($this->width / $cell));
        $gh   = max(1, (int)ceil($this->height / $cell));
        $grid = array_fill(0, $gw * $gh, -1);

        $attempts = (int)$this->options['attempts'];
        $margin   = (int)$this->options['margin'];
        $floats   = (bool)$this->options['returnFloats'];

        $pts    = [];
        $active = [];

        $add = function (float $x, float $y) use (&$pts, &$active, &$grid, $cell, $gw, $gh) {
            $idx                   = count($pts);
            $pts[]                 = [$x, $y];
            $active[]              = $idx;
            $gx                    = (int)($x / $cell);
            $gy                    = (int)($y / $cell);
            $gx                    = max(0, min($gw - 1, $gx));
            $gy                    = max(0, min($gh - 1, $gy));
            $grid[$gy * $gw + $gx] = $idx;
        };

        // seed with one random point
        $add($this->rf($margin, $this->width - $margin), $this->rf($margin, $this->height - $margin));

        // Bridson-ish loop (imperfect but fine to start)
        while (!empty($active) && count($pts) < $this->count) {
            $ai         = $this->randomizer->getInt(0, count($active) - 1);
            [$px, $py]  = $pts[$active[$ai]];
            $placed     = false;

            for ($i = 0; $i < $attempts; $i++) {
                $u   = $this->randomizer->nextFloat();
                $ang = 2.0 * M_PI * $this->randomizer->nextFloat();      // [0,1)
                $rad = $r * sqrt(1.0 + (3.0 * $u));                 // [r,2r)
                $x   = $px + $rad * cos($ang);
                $y   = $py + $rad * sin($ang);

                if ($x < $margin || $y < $margin || $x >= $this->width - $margin || $y >= $this->height - $margin) {
                    continue;
                }

                $gx = (int)($x / $cell);
                $gy = (int)($y / $cell);
                $ok = true;
                for ($yy = max(0, $gy - 2); $yy <= min($gh - 1, $gy + 2); $yy++) {
                    for ($xx = max(0, $gx - 2); $xx <= min($gw - 1, $gx + 2); $xx++) {
                        $q = $grid[$yy * $gw + $xx];
                        if ($q < 0) {
                            continue;
                        }
                        [$qx, $qy] = $pts[$q];
                        $dx = $qx - $x;
                        $dy = $qy - $y;
                        $r2 = $r * $r;
                        if (($dx * $dx + $dy * $dy) < ($r2)) {
                            $ok = false;
                            break 2;
                        }
                    }
                }

                if ($ok) {
                    $add($x, $y);
                    $placed = true;
                    break;
                }
            }

            if (!$placed) {
                array_splice($active, $ai, 1); // retire this seed
            }
        }

        if ($floats) {
            return [
                'points' => $pts,
                'r'      => $r
            ];
        }
        $snapped = array_map(fn($p) => [(int)round($p[0]), (int)round($p[1])], $pts);
        $uniq    = [];
        foreach ($snapped as $p) {
            $uniq[$p[0] . ',' . $p[1]] = $p;
        }
        if (config('game_config.feature.persist_data')) {
            PointOfInterest::createPointsForGalaxy($galaxy, array_values($uniq));
        }
        return array_values($uniq);
    }

    /**
     * @param float $spacing_factor
     * @return float
     */
    private function radius(float $spacing_factor): float
    {
        return max(1.0, $spacing_factor); // treat spacingFactor as direct minimum spacing
    }

    /**
     * @param float $a
     * @param float $b
     * @return float
     */
    private function rf(float $a, float $b): float
    {
        return $a + $this->randomizer->nextFloat() * ($b - $a);
    }
}
