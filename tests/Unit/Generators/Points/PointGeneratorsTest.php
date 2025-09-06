<?php

namespace Tests\Unit\Generators\Points;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use App\Generators\Points\PoissonDisk;
use App\Generators\Points\RandomScatter;
use App\Generators\Points\HaltonSequence;
use App\Contracts\PointGeneratorInterface;

class PointGeneratorsTest extends TestCase
{
    private const int WIDTH = 100;
    private const int HEIGHT = 100;
    private const int COUNT = 50;
    private const float SPACING = 5.0;
    private const int SEED = 12345;
    private const array OPTIONS = [
        'attempts' => 30,
        'margin' => 0,
        'returnFloats' => false,
    ];

    /**
     * Data provider for all generators.
     *
     * @return array<string, array{class-string<PointGeneratorInterface>}>
     */
    public static function generatorProvider(): array
    {
        return [
            'PoissonDisk' => [PoissonDisk::class],
            'RandomScatter' => [RandomScatter::class],
            'HaltonSequence' => [HaltonSequence::class],
        ];
    }

    /**
     * @dataProvider generatorProvider
     */
    #[DataProvider('generatorProvider')]
    public function testGeneratorsProduceValidPoints(string $generatorClass): void
    {
        $generator = new $generatorClass(
            self::WIDTH,
            self::HEIGHT,
            self::COUNT,
            self::SPACING,
            self::SEED,
            self::OPTIONS,
        );

        $points = $generator->sample();

        // Count matches requested
        $this->assertCount(
            self::COUNT,
            $points,
            "$generatorClass did not produce expected number of points"
        );

        // All points within bounds
        foreach ($points as [$x, $y]) {
            $this->assertGreaterThanOrEqual(0, $x, "$generatorClass produced out-of-bounds X");
            $this->assertGreaterThanOrEqual(0, $y, "$generatorClass produced out-of-bounds Y");
            $this->assertLessThan(self::WIDTH, $x, "$generatorClass produced out-of-bounds X");
            $this->assertLessThan(self::HEIGHT, $y, "$generatorClass produced out-of-bounds Y");
        }

        // No duplicates
        $unique = array_unique(array_map(fn($p) => $p[0] . ':' . $p[1], $points));
        $this->assertCount(
            count($points),
            $unique,
            "$generatorClass produced duplicate points"
        );
    }

    #[DataProvider('generatorProvider')]
    public function testDeterministicWithSeed(string $generatorClass): void
    {
        $gen1 = new $generatorClass(
            self::WIDTH,
            self::HEIGHT,
            self::COUNT,
            self::SPACING,
            self::SEED,
            self::OPTIONS,
        );

        $gen2 = new $generatorClass(
            self::WIDTH,
            self::HEIGHT,
            self::COUNT,
            self::SPACING,
            self::SEED,
            self::OPTIONS,
        );

        $points1 = $gen1->sample();
        $points2 = $gen2->sample();

        $this->assertSame(
            $points1,
            $points2,
            "$generatorClass did not produce deterministic results with same seed"
        );
    }
}
