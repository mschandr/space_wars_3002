<?php

namespace App\Enums\Economy;

enum ShockType: string
{
    case DISCOVERY = 'DISCOVERY';      // New deposit found
    case BLOCKADE = 'BLOCKADE';        // Supply cut off
    case DISASTER = 'DISASTER';        // Production destroyed
    case BOOM = 'BOOM';                // Demand surge
    case CRASH = 'CRASH';              // Price collapse

    public function label(): string
    {
        return match ($this) {
            self::DISCOVERY => 'Discovery (New deposit)',
            self::BLOCKADE => 'Blockade (Supply cut off)',
            self::DISASTER => 'Disaster (Production lost)',
            self::BOOM => 'Boom (Demand surge)',
            self::CRASH => 'Crash (Price collapse)',
        };
    }

    public function defaultMagnitude(): float
    {
        return match ($this) {
            self::DISCOVERY => 0.25,    // +25% price
            self::BLOCKADE => -0.40,    // -40% (shortage)
            self::DISASTER => -0.30,    // -30% (supply shock)
            self::BOOM => 0.35,         // +35% (demand)
            self::CRASH => -0.50,       // -50% (collapse)
        };
    }

    public function defaultHalfLife(): int
    {
        return match ($this) {
            self::DISCOVERY => 100,     // 100 ticks to half strength
            self::BLOCKADE => 150,      // Blockades last longer
            self::DISASTER => 80,       // Quick recovery
            self::BOOM => 120,          // Boom lasts a while
            self::CRASH => 60,          // Quick rebound
        };
    }
}
