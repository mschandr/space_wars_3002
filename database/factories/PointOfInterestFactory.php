<?php

namespace Database\Factories;

use App\Enums\PointsOfInterest\PointOfInterestStatus;
use App\Enums\PointsOfInterest\PointOfInterestType;
use App\Models\Galaxy;
use App\Models\PointOfInterest;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PointOfInterest>
 */
class PointOfInterestFactory extends Factory
{
    protected $model = PointOfInterest::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'galaxy_id' => Galaxy::factory(),
            'sector_id' => null,
            'parent_poi_id' => null,
            'orbital_index' => null,
            'type' => PointOfInterestType::STAR,
            'status' => PointOfInterestStatus::DRAFT,
            'x' => fake()->numberBetween(0, 1000),
            'y' => fake()->numberBetween(0, 1000),
            'name' => fake()->words(2, true) . ' Star',
            'attributes' => [],
            'is_hidden' => false,
            'version' => '',
        ];
    }

    /**
     * Active POI
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PointOfInterestStatus::ACTIVE,
        ]);
    }

    /**
     * Planet POI
     */
    public function planet(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => PointOfInterestType::TERRESTRIAL,
            'name' => fake()->words(2, true) . ' Planet',
        ]);
    }
}
