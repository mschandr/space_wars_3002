<?php

namespace Database\Factories;

use App\Enums\WarpGate\GateType;
use App\Models\Galaxy;
use App\Models\PointOfInterest;
use App\Models\WarpGate;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WarpGate>
 */
class WarpGateFactory extends Factory
{
    protected $model = WarpGate::class;

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
            'source_poi_id' => PointOfInterest::factory(),
            'destination_poi_id' => PointOfInterest::factory(),
            'is_hidden' => fake()->boolean(20), // 20% chance hidden
            'distance' => null, // Will be calculated on save
            'status' => 'active',
            'gate_type' => GateType::STANDARD,
        ];
    }

    /**
     * Indicate that the gate is a mirror universe entry gate.
     */
    public function mirrorEntry(): static
    {
        return $this->state(fn (array $attributes) => [
            'gate_type' => GateType::MIRROR_ENTRY,
            'is_hidden' => true, // Mirror gates are always hidden
        ]);
    }

    /**
     * Indicate that the gate is a mirror universe return gate.
     */
    public function mirrorReturn(): static
    {
        return $this->state(fn (array $attributes) => [
            'gate_type' => GateType::MIRROR_RETURN,
            'is_hidden' => true,
        ]);
    }

    /**
     * Indicate that the gate is hidden.
     */
    public function hidden(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_hidden' => true,
        ]);
    }

    /**
     * Indicate that the gate is visible.
     */
    public function visible(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_hidden' => false,
        ]);
    }
}
