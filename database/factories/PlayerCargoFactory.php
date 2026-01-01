<?php

namespace Database\Factories;

use App\Models\Mineral;
use App\Models\PlayerCargo;
use App\Models\PlayerShip;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PlayerCargo>
 */
class PlayerCargoFactory extends Factory
{
    protected $model = PlayerCargo::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'player_ship_id' => PlayerShip::factory(),
            'mineral_id' => Mineral::factory(),
            'quantity' => fake()->numberBetween(10, 100),
        ];
    }

    /**
     * Large quantity
     */
    public function large(): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity' => fake()->numberBetween(500, 1000),
        ]);
    }

    /**
     * Small quantity
     */
    public function small(): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity' => fake()->numberBetween(1, 10),
        ]);
    }
}
