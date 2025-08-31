<?php

namespace App\Support;

use Random\Engine\PcgOneseq128XslRr64;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;
use Random\Engine\Mt19937;

/**
 * Poisson-disk sample (Bridson-style)
 *
 * This class produces ~N points of interest over an area (defined by XY or Height, Width) each
 * point of interest will be at least a distance of r which is a tunable number
 *
 * @author Mark Dhas
 * @used by the galaxy factory
 *
 * @var
 * @property    int   $width            Map width
 * @property    int   $height           Map height
 * @property    int   $points           Target number of points
 * @property    float $spacing_factor   Spacing factor between 0-1.0. (Higher more spread)
 * @property    array $options          Any additional options
 */

final class PoissonDisk
{
    private int $height = 300;
    private int $width = 300;
    private int $points = 3000;
    private float $spacing_factor = 0.75;
    private array $options = [
        'attempts'      => 30,
        'margin'        => 0,
        'returnFloats'  => false,
    ];
    private ?Randomizer $rng = null;

    /**
     * Used to configure the class itself,
     *
     * @param int $w Width of the galaxy
     * @param int $h Height of the galaxy
     * @param int $N Points of interest
     * @param float $k Spacing factor
     * @param int $seed This is the seeding integer
     * @param array $opts Basic configuration options
     *
     * @author Mark Dhas
     * @branch Introduced in Feat/Galaxies
     *
     */
    public function __construct(
        int   $w,
        int   $h,
        int   $N,
        float $k,
        int   $seed,
        array $opts = []
    )
    {
        $this->width = max(1, $w);
        $this->height = max(1, $h);
        $this->points = max(1, $N);
        $this->spacing_factor = $k;

        $this->options = array_replace($this->options, $opts);
        $this->options['margin'] = max(
            0,
            min((int)$this->options['margin'], (int)floor(min($this->width,$this->height)/4))
        );
        $engineKey = config('game_config.random.engine', 'mt19937');
        $engine = match ($engineKey) {
          'pcg'     => new PcgOneseq128XslRr64($seed),
          'xoshiro' => new Xoshiro256StarStar($seed),
          default   => new Mt19937($seed),
        };
        $this->rng = new Randomizer($engine);
    }

    /**
     * @return array<int,array{0:float,1:float}> | array{points:array<int,array{0:int,1:int}>,r:float}
     */
    public function sample(): array
    {
        // --- derive radius & grid (trimmed; but not hyper-optimized) ---
        $r = $this->radius($this->width, $this->height, $this->points, $this->spacing_factor);
        $cell = $r / sqrt(2.0);
        $gw = max(1, (int)ceil($this->width / $cell));
        $gh = max(1, (int)ceil($this->height / $cell));
        $grid = array_fill(0, $gw * $gh, -1);

        $attempts = (int)$this->options['attempts'];
        $margin = (int)$this->options['margin'];
        $floats = (bool)$this->options['returnFloats'];

        $pts    = [];
        $active = [];

        $add = function (float $x, float $y) use (&$pts, &$active, &$grid, $cell, $gw, $gh) {
            $idx                    = count($pts);
            $pts[]                  = [$x, $y];
            $active[]               = $idx;
            $gx                     = (int)($x / $cell);
            $gy                     = (int)($y / $cell);
            $gx                     = max(0, min($gw -1, $gx));
            $gy                     = max(0, min($gh -1, $gy));
            $grid[$gy * $gw + $gx]  = $idx;
        };

        // seed with one random point
        $add($this->rf($margin, $this->width - $margin), $this->rf($margin, $this->height - $margin));

        // Bridson-ish loop (imperfect but fine to start)
        while (!empty($active) && count($pts) < $this->points) {
            $ai = $this->rng->getInt(0, count($active) - 1);
            [$px, $py] = $pts[$active[$ai]];
            $placed = false;

            for ($i = 0; $i < $attempts; $i++) {
                $u   = $this->rng->nextFloat();
                $ang = 2.0 * M_PI * $this->rng->nextFloat();     // [0,1)
                $rad = $r * sqrt(1.0 + (3.0 * $u));         // [r,2r)
                $x = $px + $rad * cos($ang);
                $y = $py + $rad * sin($ang);

                if ($x < $margin || $y < $margin || $x >= $this->width - $margin || $y >= $this->height - $margin) {
                    continue;
                }

                $gx = (int)($x / $cell);
                $gy = (int)($y / $cell);
                $ok = true;
                for ($yy = max(0, $gy - 2); $yy <= min($gh - 1, $gy + 2); $yy++) {
                    for ($xx = max(0, $gx - 2); $xx <= min($gw - 1, $gx + 2); $xx++) {
                        $q              = $grid[$yy * $gw + $xx];
                        if ($q < 0) {
                            continue;
                        }
                        [$qx, $qy]      = $pts[$q];
                        $dx             = $qx - $x;
                        $dy             = $qy - $y;
                        $r2             = $r*$r;
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

        if ($floats) return ['points' => $pts, 'r'=>$r];
        $snapped = array_map(fn($p) => [(int)round($p[0]), (int)round($p[1])], $pts);
        $uniq = [];
        foreach ($snapped as $p) {
            $uniq[$p[0] . ',' . $p[1]] = $p;
        }
        return ['points' => array_values($uniq), 'r' => $r];
    }

    public function radius(int $width, int $height, int $points, float $spacing_factor): float
    {
        $area = max(1, $width * $height);
        return max(1.0, $spacing_factor * sqrt($area / max(1, $points)));
    }

    private function rf(float $a, float $b): float
    {
        return $a + $this->rng->nextFloat() * ($b - $a);
    }
}
