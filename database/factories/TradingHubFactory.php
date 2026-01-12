<?php

namespace Database\Factories;

use App\Models\PointOfInterest;
use App\Models\TradingHub;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TradingHub>
 */
class TradingHubFactory extends Factory
{
    protected $model = TradingHub::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => Str::uuid(),
            'poi_id' => PointOfInterest::factory(),
            'name' => fake()->company().' Trading Post',
            'type' => fake()->randomElement(['standard', 'major', 'premium']),
            'has_salvage_yard' => fake()->boolean(30), // 30% chance of having salvage yard
            'gate_count' => fake()->numberBetween(1, 5),
            'tax_rate' => fake()->randomFloat(2, 5.00, 15.00),
            'services' => null,
            'attributes' => null,
            'is_active' => true,
        ];
    }

    /**
     * Major trading hub
     */
    public function major(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'major',
            'tax_rate' => 8.00,
            'gate_count' => fake()->numberBetween(3, 6),
        ]);
    }

    /**
     * Premium trading hub
     */
    public function premium(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'premium',
            'tax_rate' => 5.00,
            'has_salvage_yard' => true,
            'gate_count' => fake()->numberBetween(5, 8),
        ]);
    }
}
