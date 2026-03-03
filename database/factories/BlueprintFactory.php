<?php

namespace Database\Factories;

use App\Models\Blueprint;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Blueprint>
 */
class BlueprintFactory extends Factory
{
    protected $model = Blueprint::class;

    public function definition(): array
    {
        return [
            'code' => $this->faker->unique()->word(),
            'name' => $this->faker->words(3, true),
            'type' => $this->faker->randomElement(['SHIP', 'HABITAT', 'MODULE', 'FACILITY']),
            'build_time_ticks' => $this->faker->numberBetween(100, 1000),
            'output_item_code' => $this->faker->word(),
        ];
    }
}
