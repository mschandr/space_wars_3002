<?php

namespace Database\Factories;

use App\Models\Colony;
use App\Models\ColonyBuilding;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ColonyBuilding>
 */
class ColonyBuildingFactory extends Factory
{
    protected $model = ColonyBuilding::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => Str::uuid(),
            'colony_id' => Colony::factory(),
            'building_type' => 'hydroponics',
            'required_stage' => 1,
            'level' => 1,
            'status' => 'operational',
            'construction_progress' => 100,
            'construction_cost_credits' => 1000,
            'construction_cost_minerals' => 100,
            'construction_cost_population' => 50,
            'construction_started_at' => now()->subDays(1),
            'construction_completed_at' => now(),
            'effects' => ['food_production' => 100],
            'credits_per_cycle' => 0,
            'quantium_per_cycle' => 0,
            'food_per_cycle' => 0,
            'minerals_per_cycle' => 0,
            'credits_generated_per_cycle' => 0,
            'last_cycle_at' => now(),
        ];
    }

    /**
     * Hydroponics building (food production)
     */
    public function hydroponics(): static
    {
        return $this->state(fn (array $attributes) => [
            'building_type' => 'hydroponics',
            'required_stage' => 1,
            'effects' => ['food_production' => 100],
        ]);
    }

    /**
     * Mining facility (mineral production)
     */
    public function miningFacility(): static
    {
        return $this->state(fn (array $attributes) => [
            'building_type' => 'mining_facility',
            'required_stage' => 2,
            'effects' => ['mineral_production' => 50],
        ]);
    }

    /**
     * Trade station (credits generation)
     */
    public function tradeStation(): static
    {
        return $this->state(fn (array $attributes) => [
            'building_type' => 'trade_station',
            'required_stage' => 2,
            'effects' => ['credits_per_cycle' => 200],
            'credits_generated_per_cycle' => 200,
        ]);
    }

    /**
     * Warp gate (requires quantium)
     */
    public function warpGate(): static
    {
        return $this->state(fn (array $attributes) => [
            'building_type' => 'warp_gate',
            'required_stage' => 5,
            'quantium_per_cycle' => 1,
            'credits_generated_per_cycle' => 600,
            'effects' => [],
        ]);
    }

    /**
     * Shipyard
     */
    public function shipyard(): static
    {
        return $this->state(fn (array $attributes) => [
            'building_type' => 'shipyard',
            'required_stage' => 3,
            'effects' => ['ship_production' => true],
        ]);
    }

    /**
     * Operational building
     */
    public function operational(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'operational',
            'construction_progress' => 100,
            'construction_completed_at' => now(),
        ]);
    }

    /**
     * Damaged building
     */
    public function damaged(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'damaged',
        ]);
    }

    /**
     * Constructing building
     */
    public function constructing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'constructing',
            'construction_progress' => fake()->numberBetween(10, 90),
            'construction_completed_at' => null,
        ]);
    }
}
