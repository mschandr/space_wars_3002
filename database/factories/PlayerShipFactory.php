<?php

namespace Database\Factories;

use App\Models\Player;
use App\Models\PlayerShip;
use App\Models\Ship;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PlayerShip>
 */
class PlayerShipFactory extends Factory
{
    protected $model = PlayerShip::class;

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
            'ship_id' => Ship::factory(),
            'current_poi_id' => null,
            'name' => null,
            'current_fuel' => 100,
            'max_fuel' => 100,
            'fuel_last_updated_at' => now(),
            'hull' => 100,
            'max_hull' => 100,
            'weapons' => 10,
            'cargo_hold' => 10,
            'sensors' => 1,
            'warp_drive' => 1,
            'current_cargo' => 0,
            'is_active' => false,
            'status' => 'operational',
        ];
    }

    /**
     * Mark this ship as the active ship
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Create a damaged ship
     */
    public function damaged(int $hullRemaining = 50): static
    {
        return $this->state(fn (array $attributes) => [
            'hull' => $hullRemaining,
            'status' => 'damaged',
        ]);
    }

    /**
     * Create a destroyed ship
     */
    public function destroyed(): static
    {
        return $this->state(fn (array $attributes) => [
            'hull' => 0,
            'status' => 'destroyed',
        ]);
    }

    /**
     * Create a ship with specific sensor level
     */
    public function withSensors(int $level): static
    {
        return $this->state(fn (array $attributes) => [
            'sensors' => $level,
        ]);
    }

    /**
     * Create a ship with specific warp drive level
     */
    public function withWarpDrive(int $level): static
    {
        return $this->state(fn (array $attributes) => [
            'warp_drive' => $level,
        ]);
    }

    /**
     * Create a ship with specific weapon level
     */
    public function withWeapons(int $level): static
    {
        return $this->state(fn (array $attributes) => [
            'weapons' => $level,
        ]);
    }

    /**
     * Create a fully upgraded ship
     */
    public function fullyUpgraded(): static
    {
        return $this->state(fn (array $attributes) => [
            'hull' => 200,
            'max_hull' => 200,
            'weapons' => 50,
            'cargo_hold' => 100,
            'sensors' => 10,
            'warp_drive' => 10,
        ]);
    }

    /**
     * Create a ship with low fuel
     */
    public function lowFuel(int $fuelRemaining = 10): static
    {
        return $this->state(fn (array $attributes) => [
            'current_fuel' => $fuelRemaining,
        ]);
    }

    /**
     * Create a ship with cargo
     */
    public function withCargo(int $amount): static
    {
        return $this->state(fn (array $attributes) => [
            'current_cargo' => min($amount, $attributes['cargo_hold'] ?? 10),
        ]);
    }
}
