<?php

namespace Database\Factories;

use App\Models\Galaxy;
use App\Models\Sector;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Sector>
 */
class SectorFactory extends Factory
{
    protected $model = Sector::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $xMin = fake()->numberBetween(0, 100);
        $yMin = fake()->numberBetween(0, 100);
        $xMax = $xMin + fake()->numberBetween(50, 100);
        $yMax = $yMin + fake()->numberBetween(50, 100);

        return [
            'uuid' => Str::uuid(),
            'galaxy_id' => Galaxy::factory(),
            'name' => fake()->randomElement(['Alpha', 'Beta', 'Gamma', 'Delta', 'Epsilon']).' Sector '.fake()->numberBetween(1, 999),
            'grid_x' => fake()->numberBetween(0, 20),
            'grid_y' => fake()->numberBetween(0, 20),
            'x_min' => $xMin,
            'x_max' => $xMax,
            'y_min' => $yMin,
            'y_max' => $yMax,
            'attributes' => null,
        ];
    }
}
