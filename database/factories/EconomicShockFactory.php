<?php

namespace Database\Factories;

use App\Models\Commodity;
use App\Models\EconomicShock;
use App\Models\Galaxy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EconomicShock>
 */
class EconomicShockFactory extends Factory
{
    protected $model = EconomicShock::class;

    public function definition(): array
    {
        return [
            'galaxy_id' => Galaxy::factory(),
            'commodity_id' => Commodity::factory(),
            'shock_type' => $this->faker->randomElement(['DISCOVERY', 'BLOCKADE', 'DISASTER', 'BOOM', 'CRASH']),
            'magnitude' => $this->faker->randomFloat(2, 0.1, 1.0),
            'decay_half_life_ticks' => $this->faker->numberBetween(100, 1000),
            'is_active' => true,
            'created_at' => now(),
        ];
    }
}
