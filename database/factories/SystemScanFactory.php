<?php

namespace Database\Factories;

use App\Models\Player;
use App\Models\PointOfInterest;
use App\Models\SystemScan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SystemScan>
 */
class SystemScanFactory extends Factory
{
    protected $model = SystemScan::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => Str::uuid(),
            'player_id' => Player::factory(),
            'poi_id' => PointOfInterest::factory(),
            'scan_level' => $this->faker->numberBetween(1, 9),
            'scan_data' => [],
            'scanned_at' => now(),
        ];
    }

    /**
     * Create a scan at a specific level.
     */
    public function atLevel(int $level): static
    {
        return $this->state(fn (array $attributes) => [
            'scan_level' => $level,
            'scan_data' => $this->generateScanDataForLevel($level),
        ]);
    }

    /**
     * Create a geography-only scan (level 1).
     */
    public function geographyOnly(): static
    {
        return $this->atLevel(1);
    }

    /**
     * Create a basic resources scan (level 3).
     */
    public function basicResources(): static
    {
        return $this->atLevel(3);
    }

    /**
     * Create a full scan (level 9).
     */
    public function fullScan(): static
    {
        return $this->atLevel(9);
    }

    /**
     * Generate sample scan data for a given level.
     */
    protected function generateScanDataForLevel(int $level): array
    {
        $data = [];

        if ($level >= 1) {
            $data['1'] = [
                'star_type' => 'G-class yellow dwarf',
                'planet_count' => $this->faker->numberBetween(1, 12),
                'planet_types' => [
                    'rocky' => $this->faker->numberBetween(0, 4),
                    'gas' => $this->faker->numberBetween(0, 3),
                    'ice' => $this->faker->numberBetween(0, 2),
                ],
                'asteroid_belts' => $this->faker->numberBetween(0, 2),
                'habitability' => [
                    'goldilocks_planets' => $this->faker->numberBetween(0, 2),
                    'notes' => [],
                ],
            ];
        }

        if ($level >= 2) {
            $data['2'] = [
                'gate_count' => $this->faker->numberBetween(0, 5),
                'active_gates' => $this->faker->numberBetween(0, 3),
                'dormant_gates' => $this->faker->numberBetween(0, 2),
                'gates' => [],
            ];
        }

        if ($level >= 3) {
            $data['3'] = [
                'rocky_planets' => ['iron', 'copper'],
                'gas_giants' => ['metallic_hydrogen', 'helium-3'],
            ];
        }

        return $data;
    }
}
