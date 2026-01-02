<?php

namespace Database\Factories;

use App\Models\Ship;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Ship>
 */
class ShipFactory extends Factory
{
    protected $model = Ship::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => Str::uuid(),
            'name' => fake()->unique()->word() . '-class',
            'class' => fake()->randomElement(['fighter', 'transport', 'hauler', 'battleship']),
            'description' => fake()->sentence(),
            'base_price' => fake()->numberBetween(5000, 100000),
            'cargo_capacity' => fake()->numberBetween(10, 200),
            'speed' => fake()->numberBetween(50, 200),
            'hull_strength' => fake()->numberBetween(50, 500),
            'shield_strength' => fake()->numberBetween(0, 200),
            'weapon_slots' => fake()->numberBetween(1, 10),
            'utility_slots' => fake()->numberBetween(1, 5),
            'rarity' => fake()->randomElement(['common', 'uncommon', 'rare', 'legendary']),
            'requirements' => null,
            'attributes' => null,
            'is_available' => true,
        ];
    }

    /**
     * Starter ship (cheap, basic stats)
     */
    public function starter(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Sparrow-class',
            'class' => 'fighter',
            'base_price' => 5000.00,
            'cargo_capacity' => 10,
            'speed' => 100,
            'hull_strength' => 100,
            'shield_strength' => 0,
            'weapon_slots' => 2,
            'utility_slots' => 1,
            'rarity' => 'common',
        ]);
    }

    /**
     * Combat ship (high weapons, low cargo)
     */
    public function combat(): static
    {
        return $this->state(fn (array $attributes) => [
            'class' => 'battleship',
            'cargo_capacity' => 20,
            'speed' => 80,
            'hull_strength' => 300,
            'shield_strength' => 150,
            'weapon_slots' => 8,
            'utility_slots' => 2,
            'rarity' => 'rare',
        ]);
    }

    /**
     * Hauler ship (huge cargo, weak combat)
     */
    public function hauler(): static
    {
        return $this->state(fn (array $attributes) => [
            'class' => 'hauler',
            'cargo_capacity' => 200,
            'speed' => 60,
            'hull_strength' => 150,
            'shield_strength' => 50,
            'weapon_slots' => 2,
            'utility_slots' => 4,
            'rarity' => 'uncommon',
        ]);
    }
}
