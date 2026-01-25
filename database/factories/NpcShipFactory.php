<?php

namespace Database\Factories;

use App\Models\Npc;
use App\Models\NpcShip;
use App\Models\Ship;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\NpcShip>
 */
class NpcShipFactory extends Factory
{
    protected $model = NpcShip::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => Str::uuid(),
            'npc_id' => Npc::factory(),
            'ship_id' => Ship::factory(),
            'name' => fake()->word().' '.fake()->randomElement(['Runner', 'Star', 'Hawk', 'Falcon', 'Ghost']),
            'current_fuel' => 100,
            'max_fuel' => 100,
            'fuel_last_updated_at' => now(),
            'hull' => 100,
            'max_hull' => 100,
            'weapons' => 10,
            'cargo_hold' => 100,
            'sensors' => 1,
            'warp_drive' => 1,
            'current_cargo' => 0,
            'is_active' => true,
            'status' => 'operational',
        ];
    }

    /**
     * Create with specific ship blueprint
     */
    public function withShip(Ship $ship): static
    {
        return $this->state(fn (array $attributes) => [
            'ship_id' => $ship->id,
            'current_fuel' => $ship->base_max_fuel ?? 100,
            'max_fuel' => $ship->base_max_fuel ?? 100,
            'hull' => $ship->base_hull ?? 100,
            'max_hull' => $ship->base_hull ?? 100,
            'weapons' => $ship->base_weapons ?? 10,
            'cargo_hold' => $ship->base_cargo ?? 100,
            'sensors' => $ship->base_sensors ?? 1,
            'warp_drive' => $ship->base_warp_drive ?? 1,
        ]);
    }

    /**
     * Upgraded ship
     */
    public function upgraded(): static
    {
        return $this->state(fn (array $attributes) => [
            'max_fuel' => 200,
            'current_fuel' => 200,
            'max_hull' => 150,
            'hull' => 150,
            'weapons' => 25,
            'cargo_hold' => 200,
            'sensors' => 3,
            'warp_drive' => 3,
        ]);
    }

    /**
     * Damaged ship
     */
    public function damaged(): static
    {
        return $this->state(fn (array $attributes) => [
            'hull' => (int) ($attributes['max_hull'] * 0.25),
            'status' => 'damaged',
        ]);
    }

    /**
     * Low fuel
     */
    public function lowFuel(): static
    {
        return $this->state(fn (array $attributes) => [
            'current_fuel' => (int) ($attributes['max_fuel'] * 0.1),
        ]);
    }

    /**
     * Inactive ship
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
