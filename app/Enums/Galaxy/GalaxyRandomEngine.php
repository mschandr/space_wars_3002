<?php

namespace App\Enums\Galaxy;

enum GalaxyRandomEngine: int
{
    case MT19937 = 0;
    case PCG     = 1;
    case XOSHIRO = 2;

    public function label(): string
    {
        return match ($this) {
            self::MT19937 => 'Mersenne Twister (mt19937)',
            self::PCG     => 'PCG',
            self::XOSHIRO => 'Xoshiro256**',
        };
    }
    /**
     * Reverse lookup of string to the class property.
     *
     * @static
     * @param   string              $engine_string
     * @return  GalaxyRandomEngine
     */
    public static function fromName(string $engine_string): GalaxyRandomEngine
    {
        return match (strtolower($engine_string)) {
            'mt19937' => self::MT19937,
            'pcg'     => self::PCG,
            'xoshiro' => self::XOSHIRO,
        };
    }
}
