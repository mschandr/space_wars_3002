<?php

namespace Database\Factories;

use App\Enums\Galaxy\GalaxyDistributionMethod;
use App\Enums\Galaxy\GalaxyRandomEngine;
use App\Enums\Galaxy\GalaxyStatus;
use App\Models\Galaxy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Galaxy>
 */
class GalaxyFactory extends Factory
{
    protected $model = Galaxy::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true).' Galaxy',
            'description' => fake()->sentence(),
            'width' => 1000,
            'height' => 1000,
            'seed' => fake()->numberBetween(1000, 9999),
            'distribution_method' => GalaxyDistributionMethod::POISSON_DISK,
            'spacing_factor' => 0.75,
            'engine' => GalaxyRandomEngine::MT19937,
            'turn_limit' => 200,
            'status' => GalaxyStatus::DRAFT,
            'version' => '1.0.0',
            'is_public' => false,
            'config' => null,
        ];
    }

    /**
     * Active galaxy
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => GalaxyStatus::ACTIVE,
        ]);
    }
}
