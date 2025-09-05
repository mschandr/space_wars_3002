<?php

namespace Database\Factories\Legacy;

use App\Models\Legacy\CelestialBodyTypes;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CelestialBodyTypes>
 */
class CelestialBodyTypeFactory extends Factory
{
    protected $model = CelestialBodyTypes::class;

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
        ];
    }
}
