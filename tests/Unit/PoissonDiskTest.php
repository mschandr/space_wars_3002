<?php

namespace Tests\Unit;

use App\Support\PoissonDisk;
use PHPUnit\Framework\TestCase;

class PoissonDiskTest extends TestCase
{
    public function testPointsRespectMinimumSpacingWithFloats()
    {
        $disk = new PoissonDisk(100, 100, 50, 0.8, 42, ['returnFloats' => true]);
        $result = $disk->sample();
        $points = $result['points'];
        $r = $result['r'];

        for ($i = 0; $i < count($points); $i++) {
            for ($j = $i + 1; $j < count($points); $j++) {
                [$x1, $y1] = $points[$i];
                [$x2, $y2] = $points[$j];
                $dx = $x1 - $x2;
                $dy = $y1 - $y2;
                $dist2 = $dx * $dx + $dy * $dy;

                // Strict: no two points closer than r
                $epsilon = 1e-6;
                $this->assertGreaterThanOrEqual(
                    $r * $r - $epsilon,
                    $dist2,
                    "Points too close together (float mode)"
                );
            }
        }
    }
}
