<?php

namespace Database\Factories;

use App\Models\Commodity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Commodity>
 */
class CommodityFactory extends Factory
{
    protected $model = Commodity::class;

    public function definition(): array
    {
        return [
            'code' => $this->faker->unique()->word(),
            'name' => $this->faker->word(),
            'category' => 'MINERAL',
            'description' => $this->faker->sentence(),
            'base_price' => $this->faker->numberBetween(100, 5000),
            'is_conserved' => true,
            'price_min_multiplier' => 0.5,
            'price_max_multiplier' => 3.0,
        ];
    }

    public function conserved(bool $conserved = true): self
    {
        return $this->state([
            'is_conserved' => $conserved,
        ]);
    }
}
