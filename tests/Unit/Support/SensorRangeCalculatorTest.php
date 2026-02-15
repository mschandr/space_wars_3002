<?php

namespace Tests\Unit\Support;

use App\Support\SensorRangeCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SensorRangeCalculatorTest extends TestCase
{
    #[DataProvider('sensorLevelRangeProvider')]
    public function test_sensor_range_formula(int $level, float $expectedRange): void
    {
        $range = SensorRangeCalculator::getRangeLY($level);
        $this->assertEquals($expectedRange, $range, "Sensor level {$level} should have range {$expectedRange} LY");
    }

    public static function sensorLevelRangeProvider(): array
    {
        return [
            'Level 1' => [1, 1.0],
            'Level 2' => [2, 2.0],
            'Level 3' => [3, 4.0],
            'Level 4' => [4, 6.0],
            'Level 5' => [5, 8.0],
            'Level 6' => [6, 10.0],
            'Level 7' => [7, 12.0],
            'Level 8' => [8, 14.0],
            'Level 9' => [9, 16.0],
        ];
    }

    public function test_minimum_sensor_level_is_one(): void
    {
        // Sensor level 0 or negative should be treated as 1
        $this->assertEquals(1.0, SensorRangeCalculator::getRangeLY(0));
        $this->assertEquals(1.0, SensorRangeCalculator::getRangeLY(-1));
    }

    public function test_range_is_dramatically_smaller_than_old_formula(): void
    {
        // Old formula: sensors * 100
        // Level 5: old = 500, new = 8
        $newRange = SensorRangeCalculator::getRangeLY(5);
        $this->assertLessThan(100, $newRange, 'New sensor range should be much smaller than old formula');
    }
}
