<?php

namespace Tests\Unit\Generators\Points;

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
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

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

    protected function setUp(): void
    {
        parent::setUp();

        // Disable data persistence for unit tests
        config()->set('game_config.feature.persist_data', false);
    }

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
            'VogelsSpiral' => [VogelsSpiral::class],
            'StratifiedGrid' => [StratifiedGrid::class],
            'LatinHypercube' => [LatinHypercube::class],
            'R2Sequence' => [R2Sequence::class],
            'UniformRandom' => [UniformRandom::class],
        ];
    }

    /**
     * @dataProvider generatorProvider
     */
    #[DataProvider('generatorProvider')]
    public function test_generators_produce_valid_points(string $generatorClass): void
    {
        // Create a mock galaxy
        $galaxy = Galaxy::factory()->make([
            'id' => 1,
            'width' => self::WIDTH,
            'height' => self::HEIGHT,
        ]);

        $generator = new $generatorClass(
            self::WIDTH,
            self::HEIGHT,
            self::COUNT,
            self::SPACING,
            self::SEED,
            self::OPTIONS,
        );

        $points = $generator->sample($galaxy);

        // Count matches requested (allowing for small variance in some generators)
        $this->assertGreaterThanOrEqual(
            self::COUNT - 5,
            count($points),
            "$generatorClass produced too few points"
        );
        $this->assertLessThanOrEqual(
            self::COUNT + 5,
            count($points),
            "$generatorClass produced too many points"
        );

        // All points within bounds
        foreach ($points as [$x, $y]) {
            $this->assertGreaterThanOrEqual(0, $x, "$generatorClass produced out-of-bounds X");
            $this->assertGreaterThanOrEqual(0, $y, "$generatorClass produced out-of-bounds Y");
            $this->assertLessThan(self::WIDTH, $x, "$generatorClass produced out-of-bounds X");
            $this->assertLessThan(self::HEIGHT, $y, "$generatorClass produced out-of-bounds Y");
        }

        // No duplicates
        $unique = array_unique(array_map(fn ($p) => $p[0].':'.$p[1], $points));
        $this->assertCount(
            count($points),
            $unique,
            "$generatorClass produced duplicate points"
        );
    }

    #[DataProvider('generatorProvider')]
    public function test_deterministic_with_seed(string $generatorClass): void
    {
        // Create mock galaxies
        $galaxy1 = Galaxy::factory()->make([
            'id' => 1,
            'width' => self::WIDTH,
            'height' => self::HEIGHT,
        ]);

        $galaxy2 = Galaxy::factory()->make([
            'id' => 2,
            'width' => self::WIDTH,
            'height' => self::HEIGHT,
        ]);

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

        $points1 = $gen1->sample($galaxy1);
        $points2 = $gen2->sample($galaxy2);

        $this->assertSame(
            $points1,
            $points2,
            "$generatorClass did not produce deterministic results with same seed"
        );
    }
}
