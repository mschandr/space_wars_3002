<?php

namespace App\Factories;

use Random\RandomException;
use App\Contracts\PointGeneratorInterface;
use App\Generators\Points\RandomScatter;
use App\Generators\Points\PoissonDisk;
use App\Generators\Points\HaltonSequence;
use App\ValueObjects\GalaxyConfig;
use App\Models\Galaxy;

class PointGeneratorFactory
{
    /**
     * @param int|null      $width
     * @param int|null      $height
     * @param int|null      $count
     * @param float|null    $spacing
     * @param int|null      $seed
     * @return PointGeneratorInterface
     * @throws RandomException
     */
    public static function make(
        ?int   $width   = null,
        ?int   $height  = null,
        ?int   $count   = null,
        ?float $spacing = null,
        ?int   $seed    = null
    ): PointGeneratorInterface
    {
        $config     = config('game_config.galaxy');
        $options    = $options ?? config('game_config.generator_options');

        $width      = $width ?? $config['width'];
        $height     = $height ?? $config['height'];
        $count      = $count ?? $config['points'];
        $spacing    = $spacing ?? $config['spacing'];
        $seed       = $seed ?? $config['seed'] ?? null;
        $engine     = $config['engine'] ?? 'mt19937';
        $type       = strtolower($config['generator']) ?? 'scatter';
        $turn_limit = $config['turn_limit'] ?? 0;
        $is_public  = $config['is_public'] ?? false;

        $config = GalaxyConfig::fromArray([
            'width'               => $width,
            'height'              => $height,
            'count'               => $count,
            'seed'                => $seed,
            'distribution_method' => $type,
            'spacing'             => $spacing,
            'engine'              => $engine,
            'turn_limit'          => $turn_limit,
            'is_public'           => $is_public,
            'config'              => $config,
        ]);

        Galaxy::createGalaxy([
            $config->toArray(),
        ]);
        return match ($type) {
            'poisson', 'poissondisk'
                    => new PoissonDisk($width, $height, $count, $spacing, $seed, $options, $engine),
            'halton', 'haltonsequence'
                    => new HaltonSequence($width, $height, $count, $spacing, $seed, $options, $engine),
            default => new RandomScatter($width, $height, $count, $spacing, $seed, $options, $engine),
        };
    }
}
