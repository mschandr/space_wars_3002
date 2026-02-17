<?php

namespace Database\Factories;

use App\Enums\RarityTier;
use App\Models\PointOfInterest;
use App\Models\Ship;
use App\Models\ShipyardInventory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ShipyardInventory>
 */
class ShipyardInventoryFactory extends Factory
{
    protected $model = ShipyardInventory::class;

    public function definition(): array
    {
        return [
            'uuid' => Str::uuid(),
            'poi_id' => PointOfInterest::factory(),
            'ship_id' => Ship::factory(),
            'name' => fake()->word().' '.fake()->word().' '.strtoupper(fake()->bothify('??##')),
            'rarity' => RarityTier::COMMON->value,
            'price' => fake()->numberBetween(5000, 100000),
            'hull_strength' => fake()->numberBetween(50, 500),
            'shield_strength' => fake()->numberBetween(0, 200),
            'cargo_capacity' => fake()->numberBetween(10, 200),
            'speed' => fake()->numberBetween(50, 200),
            'weapon_slots' => fake()->numberBetween(1, 10),
            'utility_slots' => fake()->numberBetween(1, 5),
            'max_fuel' => fake()->numberBetween(50, 200),
            'sensors' => fake()->numberBetween(1, 5),
            'warp_drive' => fake()->numberBetween(1, 5),
            'weapons' => fake()->numberBetween(5, 50),
            'variation_traits' => null,
            'attributes' => null,
            'is_sold' => false,
        ];
    }

    public function common(): static
    {
        return $this->state(fn () => ['rarity' => RarityTier::COMMON->value]);
    }

    public function uncommon(): static
    {
        return $this->state(fn () => ['rarity' => RarityTier::UNCOMMON->value]);
    }

    public function rare(): static
    {
        return $this->state(fn () => ['rarity' => RarityTier::RARE->value]);
    }

    public function epic(): static
    {
        return $this->state(fn () => ['rarity' => RarityTier::EPIC->value]);
    }

    public function unique(): static
    {
        return $this->state(fn () => ['rarity' => RarityTier::UNIQUE->value]);
    }

    public function exotic(): static
    {
        return $this->state(fn () => ['rarity' => RarityTier::EXOTIC->value]);
    }

    public function sold(): static
    {
        return $this->state(fn () => ['is_sold' => true]);
    }
}
