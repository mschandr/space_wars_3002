<?php

namespace App\Enums\Galaxy;

enum GalaxyDistributionMethod: int
{
    case RANDOM_SCATTER = 0;
    case POISSON_DISK   = 1;
    case HALTON_SEQ     = 2;

    public function label(): string
    {
        return match ($this) {
            self::RANDOM_SCATTER => 'Random Scatter',
            self::POISSON_DISK   => 'Poisson Disk',
            self::HALTON_SEQ     => 'Halton Sequence',
        };
    }

    public static function fromName(string $distribution_string): GalaxyDistributionMethod
    {
        return match (strtolower($distribution_string)) {
            'scatter' => self::RANDOM_SCATTER,
            'poisson' => self::POISSON_DISK,
            'halton'  => self::HALTON_SEQ,
        };
    }
}
