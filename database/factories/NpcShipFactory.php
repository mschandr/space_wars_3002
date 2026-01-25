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
     * Default attributes for creating an NpcShip model.
     *
     * Notable fields:
     * - `uuid`: generated UUID.
     * - `npc_id`: created via Npc factory.
     * - `ship_id`: created via Ship factory.
     * - `name`: composed fake word plus a ship-type suffix.
     * - Ship stats and resources defaulted (fuel, hull, weapons, cargo, sensors, warp).
     * - `fuel_last_updated_at`: set to the current time.
     * - `is_active`: true, `status`: "operational".
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
     * Set the factory state to use the provided Ship as the blueprint and synchronize core ship attributes.
     *
     * Uses the Ship's `base_*` values for fuel, hull, weapons, cargo, sensors, and warp drive, falling back to sensible defaults when those base values are null.
     *
     * @param Ship $ship The Ship model to use as a blueprint for the generated NpcShip.
     * @return static A factory configured to create an NpcShip associated with the given Ship and matching its core attributes.
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
     * Configure the factory to produce an upgraded ship with enhanced capabilities.
     *
     * @return static The factory instance configured to set higher fuel, hull, weapons, cargo hold, sensors, and warp drive values for an upgraded ship.
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
     * Apply a damaged state to the factory.
     *
     * Sets `hull` to 25% of `max_hull` (cast to int) and `status` to `'damaged'`.
     *
     * @return static The factory configured with damaged hull and status.
     */
    public function damaged(): static
    {
        return $this->state(fn (array $attributes) => [
            'hull' => (int) ($attributes['max_hull'] * 0.25),
            'status' => 'damaged',
        ]);
    }

    /**
     * Configure the factory so the ship has low fuel.
     *
     * Sets `current_fuel` to 10% of `max_fuel` (rounded down).
     *
     * @return static A factory configured with `current_fuel` reduced to 10% of `max_fuel`.
     */
    public function lowFuel(): static
    {
        return $this->state(fn (array $attributes) => [
            'current_fuel' => (int) ($attributes['max_fuel'] * 0.1),
        ]);
    }

    /**
     * Configure the factory to produce an inactive ship.
     *
     * @return static The factory state where `is_active` is false.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}