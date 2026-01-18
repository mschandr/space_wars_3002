<?php

namespace Database\Factories;

use App\Models\Galaxy;
use App\Models\PirateFaction;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PirateFaction>
 */
class PirateFactionFactory extends Factory
{
    protected $model = PirateFaction::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => Str::uuid(),
            'galaxy_id' => Galaxy::factory(),
            'name' => fake()->unique()->company().' Pirates', // Make it unique
            'description' => fake()->sentence(),
            'attributes' => null,
            'is_active' => true,
        ];
    }
}
