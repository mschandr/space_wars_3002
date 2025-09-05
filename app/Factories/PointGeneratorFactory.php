<?php

namespace App\Factories;

use App\Contracts\PointGeneratorInterface;
use App\Generators\Points\RandomScatter;
use App\Generators\Points\PoissonDisk;
use App\Generators\Points\HaltonSequence;
use InvalidArgumentException;
use Random\RandomException;

class PointGeneratorFactory
{
    /**
     * @throws RandomException
     */
    public static function make(
        ?int $width     = null,
        ?int $height    = null,
        ?int $count     = null,
        ?float $spacing = null,
        ?int $seed      = null
    ): PointGeneratorInterface {
        $config   = config('game_config.galaxy');
        $options  = config('game_config.generator_options', []);

        $width   = $width   ?? $config['width'];
        $height  = $height  ?? $config['height'];
        $count   = $count   ?? $config['points'];
        $spacing = $spacing ?? $config['spacing'];
        $seed    = $seed    ?? $config['seed'] ?? null;
        $engine  = $config['engine']    ?? 'mt19937';
        $type    = strtolower($config['generator'] ?? 'scatter');

        return match ($type) {
            'poisson',  'poissondisk'
                => new PoissonDisk($width, $height, $count, $spacing, $seed, $options, $engine),
            'halton',   'haltonsequence'
                => new HaltonSequence($width, $height, $count, $spacing, $seed, $options, $engine),
            default => new RandomScatter($width, $height, $count, $spacing, $seed, $options, $engine),
        };
    }
}
