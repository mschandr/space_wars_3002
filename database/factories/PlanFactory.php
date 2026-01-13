<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public function definition(): array
    {
        $components = ['weapons', 'sensors', 'warp_drive', 'cargo_hold', 'hull', 'shields'];
        $rarities = ['common', 'uncommon', 'rare', 'epic', 'legendary'];

        $component = fake()->randomElement($components);

        return [
            'name' => fake()->words(2, true),
            'component' => $component,
            'description' => fake()->sentence(),
            'additional_levels' => fake()->numberBetween(1, 10),
            'price' => fake()->randomFloat(2, 1000, 50000),
            'rarity' => fake()->randomElement($rarities),
            'requirements' => [
                'min_level' => fake()->numberBetween(1, 10),
            ],
        ];
    }
}
