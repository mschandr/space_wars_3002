<?php

namespace Database\Factories;

use App\Models\Colony;
use App\Models\Player;
use App\Models\PointOfInterest;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Colony>
 */
class ColonyFactory extends Factory
{
    protected $model = Colony::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => Str::uuid(),
            'player_id' => Player::factory(),
            'poi_id' => PointOfInterest::factory(),
            'name' => fake()->words(2, true) . ' Colony',
            'population' => fake()->numberBetween(500, 5000),
            'population_growth_rate' => fake()->randomFloat(2, 0.01, 0.05),
            'max_population' => 10000,
            'food_production' => fake()->numberBetween(100, 500),
            'food_storage' => fake()->numberBetween(50, 200),
            'mineral_production' => fake()->numberBetween(50, 200),
            'mineral_storage' => fake()->numberBetween(50, 300),
            'quantium_storage' => fake()->numberBetween(10, 100),
            'credits_per_cycle' => fake()->numberBetween(100, 500),
            'development_level' => 1,
            'habitability_rating' => fake()->randomFloat(2, 0.5, 1.5),
            'status' => 'established',
            'established_at' => now()->subDays(fake()->numberBetween(1, 30)),
            'last_growth_at' => now(),
        ];
    }

    /**
     * New colony (just established)
     */
    public function establishing(): static
    {
        return $this->state(fn (array $attributes) => [
            'population' => 100,
            'development_level' => 1,
            'food_production' => 50,
            'mineral_production' => 0,
            'credits_per_cycle' => 0,
            'status' => 'establishing',
            'established_at' => now(),
        ]);
    }

    /**
     * Growing colony
     */
    public function growing(): static
    {
        return $this->state(fn (array $attributes) => [
            'population' => fake()->numberBetween(1000, 3000),
            'development_level' => fake()->numberBetween(2, 4),
            'status' => 'growing',
        ]);
    }

    /**
     * Established colony
     */
    public function established(): static
    {
        return $this->state(fn (array $attributes) => [
            'population' => fake()->numberBetween(5000, 9000),
            'development_level' => fake()->numberBetween(5, 8),
            'status' => 'established',
        ]);
    }

    /**
     * High habitability colony
     */
    public function highHabitability(): static
    {
        return $this->state(fn (array $attributes) => [
            'habitability_rating' => fake()->randomFloat(2, 1.2, 1.5),
        ]);
    }

    /**
     * Low habitability colony
     */
    public function lowHabitability(): static
    {
        return $this->state(fn (array $attributes) => [
            'habitability_rating' => fake()->randomFloat(2, 0.3, 0.7),
        ]);
    }

    /**
     * Colony at max population
     */
    public function maxPopulation(): static
    {
        return $this->state(fn (array $attributes) => [
            'population' => 10000,
            'max_population' => 10000,
        ]);
    }
}
