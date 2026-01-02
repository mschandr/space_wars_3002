<?php

namespace Database\Factories;

use App\Models\PirateCaptain;
use App\Models\PirateFleet;
use App\Models\Ship;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PirateFleet>
 */
class PirateFleetFactory extends Factory
{
    protected $model = PirateFleet::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $shipNames = [
            'The Crimson Dagger',
            'Shadow\'s Edge',
            'Void Reaver',
            'Dark Star',
            'Blood Moon',
            'Iron Fang',
            'Phantom Blade',
            'Storm Chaser',
            'Black Serpent',
            'Death\'s Hand',
        ];

        return [
            'uuid' => Str::uuid(),
            'captain_id' => PirateCaptain::factory(), // Create a captain automatically
            'ship_id' => Ship::factory(),
            'ship_name' => fake()->randomElement($shipNames),
            'hull' => 100,
            'max_hull' => 100,
            'weapons' => 20,
            'speed' => 100,
            'warp_drive' => 1,
            'cargo_capacity' => 50,
            'status' => 'operational',
        ];
    }

    /**
     * Create a weak pirate (low stats)
     */
    public function weak(): static
    {
        return $this->state(fn (array $attributes) => [
            'hull' => 50,
            'max_hull' => 50,
            'weapons' => 10,
        ]);
    }

    /**
     * Create a strong pirate (high stats)
     */
    public function strong(): static
    {
        return $this->state(fn (array $attributes) => [
            'hull' => 200,
            'max_hull' => 200,
            'weapons' => 50,
        ]);
    }

    /**
     * Create a damaged pirate
     */
    public function damaged(int $hullRemaining = 30): static
    {
        return $this->state(fn (array $attributes) => [
            'hull' => $hullRemaining,
            'status' => 'damaged',
        ]);
    }

    /**
     * Create a destroyed pirate
     */
    public function destroyed(): static
    {
        return $this->state(fn (array $attributes) => [
            'hull' => 0,
            'status' => 'destroyed',
        ]);
    }

    /**
     * Create a pirate with specific weapons
     */
    public function withWeapons(int $weapons): static
    {
        return $this->state(fn (array $attributes) => [
            'weapons' => $weapons,
        ]);
    }

    /**
     * Create a pirate with specific hull
     */
    public function withHull(int $hull, ?int $maxHull = null): static
    {
        return $this->state(fn (array $attributes) => [
            'hull' => $hull,
            'max_hull' => $maxHull ?? $hull,
        ]);
    }
}
