<?php

namespace App\Generators\Points;

use App\Contracts\PointGeneratorInterface;
use App\Models\Galaxy;
use Random\Engine\Mt19937;
use Random\Engine\PcgOneseq128XslRr64;
use Random\Engine\Xoshiro256StarStar;
use Random\RandomException;
use Random\Randomizer;

abstract class AbstractPointGenerator implements PointGeneratorInterface
{
    protected int $width = 300;

    protected int $height = 300;

    protected int $count = 3000;

    protected float $spacingFactor = 0.75;

    protected int $seed;

    protected array $options = [
        'attempts' => 30,
        'margin' => 0,
        'returnFloats' => false,
    ];

    protected string $engineKey;

    protected ?Randomizer $randomizer = null;

    /**
     * Used to configure the class itself.
     *
     * @param  int  $width  Width of the galaxy
     * @param  int  $height  Height of the galaxy
     * @param  int  $points_of_interest  Points of interest
     * @param  float  $spacing_factor  Spacing factor
     * @param  int|null  $seed  Seeding integer
     * @param  array  $options  Basic configuration options
     * @param  string  $engineKey  Randomization engine to use
     *
     * @throws RandomException
     */
    public function __construct(
        int $width,
        int $height,
        int $points_of_interest,
        float $spacing_factor,
        ?int $seed = null,
        array $options = [],
        string $engineKey = 'mt19937'
    ) {
        $this->width = $width;
        $this->height = $height;
        $this->count = $points_of_interest;
        $this->spacingFactor = $spacing_factor;
        $this->seed = $seed ?? random_int(PHP_INT_MIN, PHP_INT_MAX);
        $this->options = $options;
        $this->engineKey = $engineKey;

        $this->initializeRng();
    }

    /**
     * Set up the random engine based on $engineKey.
     */
    protected function initializeRng(): void
    {
        if (function_exists('app') && app()?->bound('config')) {
            $this->engineKey = config('game_config.random.engine', $this->engineKey);
        }
        $engine = match ($this->engineKey) {
            'pcg' => new PcgOneseq128XslRr64($this->seed),
            'xoshiro' => new Xoshiro256StarStar($this->seed),
            default => new Mt19937($this->seed),
        };

        $this->randomizer = new Randomizer($engine);
    }

    /**
     * Enforce uniqueness of points.
     *
     * @param  array<int,array{0:int,1:int}>  $points
     * @return array<int,array{0:int,1:int}>
     */
    protected function unique(array $points): array
    {
        $hash = [];

        return array_values(array_filter($points, function ($pt) use (&$hash) {
            $key = $pt[0].':'.$pt[1];
            if (isset($hash[$key])) {
                return false;
            }
            $hash[$key] = true;

            return true;
        }));
    }

    protected function isFarEnough(array $p, array $pts): bool
    {
        $minSpacing = max(1, ceil($this->spacingFactor));
        foreach ($pts as [$qx, $qy]) {
            $dx = $p[0] - $qx;
            $dy = $p[1] - $qy;
            if (($dx * $dx + $dy * $dy) < ($minSpacing * $minSpacing)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Must be implemented by each generator.
     *
     * @return array<int,array{0:int,1:int}>
     */
    abstract public function sample(Galaxy $galaxy): array;
}
