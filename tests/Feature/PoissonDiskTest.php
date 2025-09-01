<?php

namespace Feature;

use App\Support\PoissonDisk;
use PHPUnit\Framework\TestCase;

class PoissonDiskTest extends TestCase
{
    /**
     * @return void
     */
    public function testGeneratesExpectedNumberOfPoints()
    {
        $disk = new PoissonDisk(100, 100, 50, 0.8, 42);
        $result = $disk->sample();
        $points = $result['points'];

        $this->assertIsArray($points);
        $this->assertLessThanOrEqual(50, count($points));
    }

    /**
     * @return void
     */
    public function testPointsWithinBounds()
    {
        $disk = new PoissonDisk(100, 100, 50, 0.8, 42);
        $result = $disk->sample();
        $points = $result['points'];

        foreach ($points as [$x, $y]) {
            $this->assertGreaterThanOrEqual(0, $x);
            $this->assertGreaterThanOrEqual(0, $y);
            $this->assertLessThan(100, $x);
            $this->assertLessThan(100, $y);
        }
    }

    /**
     * @return void
     */
public function testPointsDoNotOverlapAfterRounding()
{
    $disk = new PoissonDisk(100, 100, 50, 0.8, 42, ['returnFloats' => false]);
    $result = $disk->sample();
    $points = $result['points'];

    $seen = [];
    foreach ($points as [$x, $y]) {
        $key = "$x,$y";
        $this->assertArrayNotHasKey($key, $seen, "Duplicate point found at ($x, $y)");
        $seen[$key] = true;
    }
}

    /**
     * @return void
     */
    public function testDeterministicSeed()
    {
        $disk1 = new PoissonDisk(100, 100, 50, 0.8, 42);
        $disk2 = new PoissonDisk(100, 100, 50, 0.8, 42);
        $this->assertSame($disk1->sample(), $disk2->sample());
    }
}
