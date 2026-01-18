<?php

namespace App\Enums\Galaxy;

enum GalaxyDistributionMethod: int
{
    case RANDOM_SCATTER = 0;
    case POISSON_DISK   = 1;
    case HALTON_SEQ     = 2;
    case VOGELS_SPIRAL  = 3;
    case STRATIFIED_GRID = 4;
    case LATIN_HYPERCUBE = 5;
    case R2_SEQUENCE    = 6;
    case UNIFORM_RANDOM = 7;

    public function label(): string
    {
        return match ($this) {
            self::RANDOM_SCATTER => 'Random Scatter',
            self::POISSON_DISK   => 'Poisson Disk',
            self::HALTON_SEQ     => 'Halton Sequence',
            self::VOGELS_SPIRAL  => 'Vogel\'s Spiral',
            self::STRATIFIED_GRID => 'Stratified Grid',
            self::LATIN_HYPERCUBE => 'Latin Hypercube',
            self::R2_SEQUENCE    => 'R2 Sequence',
            self::UNIFORM_RANDOM => 'Uniform Random',
        };
    }

    public function getName(): string
    {
        return match ($this) {
            self::RANDOM_SCATTER => 'scatter',
            self::POISSON_DISK   => 'poisson',
            self::HALTON_SEQ     => 'halton',
            self::VOGELS_SPIRAL  => 'vogel',
            self::STRATIFIED_GRID => 'stratified',
            self::LATIN_HYPERCUBE => 'latin',
            self::R2_SEQUENCE    => 'r2',
            self::UNIFORM_RANDOM => 'uniform',
        };
    }

    public static function fromName(string $distribution_string): GalaxyDistributionMethod
    {
        return match (strtolower($distribution_string)) {
            'scatter'   => self::RANDOM_SCATTER,
            'poisson'   => self::POISSON_DISK,
            'halton'    => self::HALTON_SEQ,
            'vogel'     => self::VOGELS_SPIRAL,
            'stratified' => self::STRATIFIED_GRID,
            'latin'     => self::LATIN_HYPERCUBE,
            'r2'        => self::R2_SEQUENCE,
            'uniform'   => self::UNIFORM_RANDOM,
        };
    }
}
