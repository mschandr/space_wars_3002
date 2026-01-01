<?php

namespace Database\Factories;

use App\Models\Mineral;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Mineral>
 */
class MineralFactory extends Factory
{
    protected $model = Mineral::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word() . 'ite',
            'symbol' => strtoupper(fake()->unique()->lexify('??')),
            'description' => fake()->sentence(),
            'rarity' => fake()->randomElement(['common', 'uncommon', 'rare', 'legendary']),
            'base_value' => fake()->randomFloat(2, 10, 500),
            'attributes' => null,
        ];
    }

    /**
     * Common mineral
     */
    public function common(): static
    {
        return $this->state(fn (array $attributes) => [
            'rarity' => 'common',
            'base_value' => fake()->randomFloat(2, 10, 50),
        ]);
    }

    /**
     * Rare mineral
     */
    public function rare(): static
    {
        return $this->state(fn (array $attributes) => [
            'rarity' => 'rare',
            'base_value' => fake()->randomFloat(2, 100, 300),
        ]);
    }

    /**
     * Legendary mineral
     */
    public function legendary(): static
    {
        return $this->state(fn (array $attributes) => [
            'rarity' => 'legendary',
            'base_value' => fake()->randomFloat(2, 500, 5000),
        ]);
    }
}
