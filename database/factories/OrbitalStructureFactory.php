<?php

namespace Database\Factories;

use App\Enums\OrbitalStructureType;
use App\Models\OrbitalStructure;
use App\Models\Player;
use App\Models\PointOfInterest;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OrbitalStructure>
 */
class OrbitalStructureFactory extends Factory
{
    protected $model = OrbitalStructure::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = OrbitalStructureType::ORBITAL_DEFENSE;

        return [
            'uuid' => Str::uuid(),
            'poi_id' => PointOfInterest::factory(),
            'player_id' => Player::factory(),
            'structure_type' => $type,
            'level' => 1,
            'status' => 'operational',
            'name' => $type->label(),
            'construction_progress' => 100,
            'construction_started_at' => now()->subDays(1),
            'construction_completed_at' => now(),
            'health' => $type->baseHealth(),
            'max_health' => $type->baseHealth(),
            'attributes' => [],
            'credits_per_cycle' => $type->operatingCosts()['credits'],
            'minerals_per_cycle' => $type->operatingCosts()['minerals'],
        ];
    }

    public function orbitalDefense(): static
    {
        $type = OrbitalStructureType::ORBITAL_DEFENSE;

        return $this->state(fn (array $attributes) => [
            'structure_type' => $type,
            'name' => $type->label(),
            'health' => $type->baseHealth(),
            'max_health' => $type->baseHealth(),
        ]);
    }

    public function magneticMine(): static
    {
        $type = OrbitalStructureType::MAGNETIC_MINE;

        return $this->state(fn (array $attributes) => [
            'structure_type' => $type,
            'name' => $type->label(),
            'health' => $type->baseHealth(),
            'max_health' => $type->baseHealth(),
        ]);
    }

    public function miningPlatform(): static
    {
        $type = OrbitalStructureType::MINING_PLATFORM;

        return $this->state(fn (array $attributes) => [
            'structure_type' => $type,
            'name' => $type->label(),
            'health' => $type->baseHealth(),
            'max_health' => $type->baseHealth(),
        ]);
    }

    public function orbitalBase(): static
    {
        $type = OrbitalStructureType::ORBITAL_BASE;

        return $this->state(fn (array $attributes) => [
            'structure_type' => $type,
            'name' => $type->label(),
            'health' => $type->baseHealth(),
            'max_health' => $type->baseHealth(),
        ]);
    }

    public function operational(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'operational',
            'construction_progress' => 100,
            'construction_completed_at' => now(),
        ]);
    }

    public function constructing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'constructing',
            'construction_progress' => fake()->numberBetween(10, 90),
            'construction_completed_at' => null,
        ]);
    }

    public function damaged(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'damaged',
        ]);
    }

    public function destroyed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'destroyed',
            'health' => 0,
        ]);
    }
}
