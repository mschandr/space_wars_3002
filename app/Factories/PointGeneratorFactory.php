<?php

namespace App\Factories;

use App\Contracts\PointGeneratorInterface;
use App\Generators\Points\HaltonSequence;
use App\Generators\Points\LatinHypercube;
use App\Generators\Points\PoissonDisk;
use App\Generators\Points\R2Sequence;
use App\Generators\Points\RandomScatter;
use App\Generators\Points\StratifiedGrid;
use App\Generators\Points\UniformRandom;
use App\Generators\Points\VogelsSpiral;
use App\Models\Galaxy;
use App\ValueObjects\GalaxyConfig;
use Random\RandomException;

class PointGeneratorFactory
{
    /**
     * @throws RandomException
     */
    public static function make(
        ?int $width = null,
        ?int $height = null,
        ?int $count = null,
        ?float $spacing = null,
        ?int $seed = null
    ): PointGeneratorInterface {
        $config = config('game_config.galaxy');
        // TODO: (Undefined Variable) $options references itself but is never defined as a parameter.
        // This always evaluates to config('game_config.generator_options'). Either add ?array $options = null
        // to the method signature, or simplify to $options = config('game_config.generator_options').
        $options = $options ?? config('game_config.generator_options');

        $width = $width ?? $config['width'];
        $height = $height ?? $config['height'];
        $count = $count ?? $config['points'];
        $spacing = $spacing ?? $config['spacing'];
        $seed = $seed ?? $config['seed'] ?? null;
        $engine = $config['engine'] ?? 'mt19937';
        $type = strtolower($config['generator']) ?? 'scatter';
        $turn_limit = $config['turn_limit'] ?? 0;
        $is_public = $config['is_public'] ?? false;

        $config = GalaxyConfig::fromArray([
            'width' => $width,
            'height' => $height,
            'count' => $count,
            'seed' => $seed,
            'distribution_method' => $type,
            'spacing' => $spacing,
            'engine' => $engine,
            'turn_limit' => $turn_limit,
            'is_public' => $is_public,
            'config' => $config,
        ]);

        Galaxy::createGalaxy([
            $config->toArray(),
        ]);

        return match ($type) {
            'poisson', 'poissondisk' => new PoissonDisk($width, $height, $count, $spacing, $seed, $options, $engine),
            'halton', 'haltonsequence' => new HaltonSequence($width, $height, $count, $spacing, $seed, $options, $engine),
            'vogel', 'vogelsspiral' => new VogelsSpiral($width, $height, $count, $spacing, $seed, $options, $engine),
            'stratified', 'stratifiedgrid' => new StratifiedGrid($width, $height, $count, $spacing, $seed, $options, $engine),
            'latin', 'latinhypercube' => new LatinHypercube($width, $height, $count, $spacing, $seed, $options, $engine),
            'r2', 'r2sequence' => new R2Sequence($width, $height, $count, $spacing, $seed, $options, $engine),
            'uniform', 'uniformrandom' => new UniformRandom($width, $height, $count, $spacing, $seed, $options, $engine),
            default => new RandomScatter($width, $height, $count, $spacing, $seed, $options, $engine),
        };
    }
}
