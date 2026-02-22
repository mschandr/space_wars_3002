<?php

namespace Database\Factories;

use App\Enums\SlotType;
use App\Models\ShipComponent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ShipComponent>
 */
class ShipComponentFactory extends Factory
{
    protected $model = ShipComponent::class;

    public function definition(): array
    {
        $slotType = fake()->randomElement(SlotType::cases());

        return [
            'uuid' => Str::uuid(),
            'name' => fake()->word().' '.fake()->randomElement(['Laser', 'Cannon', 'Shield', 'Scanner', 'Booster']),
            'type' => fake()->randomElement(['weapon', 'shield', 'hull', 'engine', 'sensor', 'fuel_system', 'cargo', 'utility']),
            'slot_type' => $slotType->value,
            'description' => fake()->sentence(),
            'slots_required' => 1,
            'base_price' => fake()->numberBetween(1000, 50000),
            'rarity' => fake()->randomElement(['common', 'uncommon', 'rare', 'epic']),
            'effects' => ['damage' => fake()->numberBetween(10, 100)],
            'requirements' => null,
            'is_available' => true,
            'max_upgrade_level' => fake()->numberBetween(0, 6),
            'upgrade_cost_base' => fake()->numberBetween(300, 15000),
            'size_class' => 'any',
        ];
    }
}
