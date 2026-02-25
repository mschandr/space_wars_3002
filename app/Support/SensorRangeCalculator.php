<?php

namespace App\Support;

/**
 * Calculates sensor detection range in light years (= coordinate units).
 *
 * New formula replaces the overly generous "sensors * 100" formula.
 * 1 coordinate unit = 1 light year.
 *
 * Level 1: 1 LY, Level 2: 2 LY, Level 3: 4 LY, Level 4: 6 LY, Level 5: 8 LY ...
 * Formula: level === 1 ? base : (level - 1) * increment
 */
class SensorRangeCalculator
{
    /**
     * Get sensor range in light years for a given sensor level.
     *
     * Uses config values from game_config.knowledge.
     */
    public static function getRangeLY(int $sensorLevel): float
    {
        $sensorLevel = max(1, $sensorLevel);

        $base = (float) config('game_config.knowledge.sensor_range_base', 1);

        if ($sensorLevel === 1) {
            return $base;
        }

        $increment = (float) config('game_config.knowledge.sensor_range_increment', 2);

        return (float) (($sensorLevel - 1) * $increment);
    }
}
