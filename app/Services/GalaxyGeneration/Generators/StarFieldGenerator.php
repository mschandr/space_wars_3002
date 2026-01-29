<?php

namespace App\Services\GalaxyGeneration\Generators;

use App\Enums\Galaxy\RegionType;
use App\Enums\PointsOfInterest\PointOfInterestStatus;
use App\Enums\PointsOfInterest\PointOfInterestType;
use App\Faker\Providers\StarNameProvider;
use App\Models\Galaxy;
use App\Services\GalaxyGeneration\Contracts\GeneratorInterface;
use App\Services\GalaxyGeneration\Data\GenerationConfig;
use App\Services\GalaxyGeneration\Data\GenerationMetrics;
use App\Services\GalaxyGeneration\Data\GenerationResult;
use App\Services\GalaxyGeneration\Support\BulkInserter;
use Illuminate\Support\Str;

/**
 * Generates star systems for a galaxy.
 *
 * Creates core stars (100% inhabited) and outer stars (0% inhabited).
 * Uses golden ratio spiral for core distribution and rejection sampling for outer.
 */
final class StarFieldGenerator implements GeneratorInterface
{
    private const STELLAR_CLASSES_CORE = ['G' => 40, 'K' => 30, 'F' => 15, 'M' => 10, 'A' => 5];

    private const STELLAR_CLASSES_OUTER = ['O' => 5, 'B' => 15, 'A' => 20, 'F' => 20, 'G' => 20, 'K' => 15, 'M' => 5];

    private const STELLAR_SIZES = ['dwarf' => 10, 'main_sequence' => 40, 'subgiant' => 25, 'giant' => 20, 'supergiant' => 5];

    public function getName(): string
    {
        return 'star_field';
    }

    public function getDependencies(): array
    {
        return []; // No dependencies - first generator
    }

    public function generate(Galaxy $galaxy, array $context = []): GenerationResult
    {
        $metrics = new GenerationMetrics;

        /** @var GenerationConfig $config */
        $config = $context['config'];
        $starCounts = $config->getStarCounts();
        $coreBounds = $config->getCoreBounds();

        $now = now();
        $version = $this->getVersion();

        // Generate core stars
        $corePoints = $this->generateCorePoints($starCounts['core'], $coreBounds);
        $coreRows = $this->buildStarRows($galaxy->id, $corePoints, RegionType::CORE, true, $now, $version);
        $metrics->setCount('core_points_generated', count($corePoints));

        // Generate outer stars
        $outerPoints = $this->generateOuterPoints($galaxy, $starCounts['outer'], $coreBounds);
        $outerRows = $this->buildStarRows($galaxy->id, $outerPoints, RegionType::OUTER, false, $now, $version);
        $metrics->setCount('outer_points_generated', count($outerPoints));

        // Bulk insert all stars
        $allRows = array_merge($coreRows, $outerRows);
        $inserted = BulkInserter::insert('points_of_interest', $allRows);

        $metrics->setCount('stars_inserted', $inserted);
        $metrics->setCount('core_stars', count($coreRows));
        $metrics->setCount('outer_stars', count($outerRows));

        return GenerationResult::success($metrics, [
            'star_count' => $inserted,
            'core_star_count' => count($coreRows),
            'outer_star_count' => count($outerRows),
        ]);
    }

    /**
     * Generate core region points using golden ratio spiral.
     */
    private function generateCorePoints(int $count, array $bounds): array
    {
        $points = [];
        $minSpacing = config('game_config.tiered_galaxy.core_min_spacing', 15);

        $width = $bounds['x_max'] - $bounds['x_min'];
        $height = $bounds['y_max'] - $bounds['y_min'];
        $centerX = $bounds['x_min'] + $width / 2;
        $centerY = $bounds['y_min'] + $height / 2;

        $goldenRatio = (1 + sqrt(5)) / 2;
        $angleIncrement = 2 * M_PI / ($goldenRatio * $goldenRatio);

        $maxAttempts = $count * 10;
        $attempts = 0;

        while (count($points) < $count && $attempts < $maxAttempts) {
            $attempts++;
            $i = count($points);

            // Spiral distribution from center
            $radius = sqrt($i / $count) * (min($width, $height) / 2 - $minSpacing);
            $angle = $i * $angleIncrement;

            $x = $centerX + $radius * cos($angle);
            $y = $centerY + $radius * sin($angle);

            // Add jitter
            $x += (random_int(-100, 100) / 100) * $minSpacing * 0.5;
            $y += (random_int(-100, 100) / 100) * $minSpacing * 0.5;

            // Clamp to bounds
            $x = max($bounds['x_min'] + 5, min($bounds['x_max'] - 5, $x));
            $y = max($bounds['y_min'] + 5, min($bounds['y_max'] - 5, $y));

            // Check minimum spacing
            if ($this->hasMinimumSpacing($points, $x, $y, $minSpacing)) {
                $points[] = [(int) round($x), (int) round($y)];
            }
        }

        return $points;
    }

    /**
     * Generate outer region points using rejection sampling.
     */
    private function generateOuterPoints(Galaxy $galaxy, int $count, array $coreBounds): array
    {
        $points = [];
        $minSpacing = config('game_config.tiered_galaxy.outer_min_spacing', 25);

        $maxAttempts = $count * 20;
        $attempts = 0;

        while (count($points) < $count && $attempts < $maxAttempts) {
            $attempts++;

            $x = random_int(10, $galaxy->width - 10);
            $y = random_int(10, $galaxy->height - 10);

            // Reject if inside core bounds
            if ($this->isInsideBounds($x, $y, $coreBounds)) {
                continue;
            }

            // Check minimum spacing
            if ($this->hasMinimumSpacing($points, $x, $y, $minSpacing)) {
                $points[] = [$x, $y];
            }
        }

        return $points;
    }

    /**
     * Build database rows for stars.
     */
    private function buildStarRows(
        int $galaxyId,
        array $points,
        RegionType $region,
        bool $inhabited,
        $now,
        ?string $version
    ): array {
        $rows = [];
        $stellarClasses = $region === RegionType::CORE ? self::STELLAR_CLASSES_CORE : self::STELLAR_CLASSES_OUTER;

        foreach ($points as $point) {
            $rows[] = [
                'uuid' => (string) Str::uuid(),
                'galaxy_id' => $galaxyId,
                'type' => PointOfInterestType::STAR->value,
                'status' => PointOfInterestStatus::ACTIVE->value,
                'x' => $point[0],
                'y' => $point[1],
                'name' => StarNameProvider::generateStarName(),
                'attributes' => json_encode([
                    'stellar_class' => $this->weightedRandom($stellarClasses),
                    'stellar_size' => $this->weightedRandom(self::STELLAR_SIZES),
                ]),
                'is_hidden' => false,
                'is_inhabited' => $inhabited,
                'region' => $region->value,
                'is_fortified' => false,
                'version' => $version,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        return $rows;
    }

    /**
     * Check if a point has minimum spacing from existing points.
     */
    private function hasMinimumSpacing(array $points, float $x, float $y, float $minSpacing): bool
    {
        foreach ($points as $existing) {
            $dx = $existing[0] - $x;
            $dy = $existing[1] - $y;
            if (sqrt($dx * $dx + $dy * $dy) < $minSpacing) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if point is inside bounds.
     */
    private function isInsideBounds(float $x, float $y, array $bounds): bool
    {
        return $x >= $bounds['x_min'] && $x <= $bounds['x_max']
            && $y >= $bounds['y_min'] && $y <= $bounds['y_max'];
    }

    /**
     * Select random value using weights.
     */
    private function weightedRandom(array $weights): string
    {
        $total = array_sum($weights);
        $roll = random_int(1, $total);
        $current = 0;

        foreach ($weights as $value => $weight) {
            $current += $weight;
            if ($roll <= $current) {
                return (string) $value;
            }
        }

        return (string) array_key_first($weights);
    }

    /**
     * Get version stamp if enabled.
     */
    private function getVersion(): ?string
    {
        if (config('game_config.feature.stamp_version', true) && file_exists(base_path('VERSION'))) {
            return trim(file_get_contents(base_path('VERSION')));
        }

        return null;
    }
}
