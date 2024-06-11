<?php

namespace Database\Factories;

use App\Models\CelestialBodyType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CelestialBodyType>
 */
class CelestialBodyTypeFactory extends Factory
{
    protected $model = CelestialBodyType::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name'        => $this->faker->name(),
            'description' => $this->faker->text(),
            'weight'      => $this->faker->randomFloat(0, 0, 9),
        ];
    }
}
