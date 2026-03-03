<?php

namespace Database\Factories;

use App\Models\Galaxy;
use App\Models\GalaxyVendorState;
use App\Models\VendorProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\GalaxyVendorState>
 */
class GalaxyVendorStateFactory extends Factory
{
    protected $model = GalaxyVendorState::class;

    public function definition(): array
    {
        return [
            'uuid' => fake()->uuid(),
            'galaxy_id' => Galaxy::inRandomOrder()->first()?->id ?? Galaxy::factory(),
            'vendor_profile_id' => VendorProfile::inRandomOrder()->first()?->id ?? VendorProfile::factory(),
            'markup_modifier' => fake()->randomFloat(4, -0.1, 0.1),  // ±10% from base
            'interaction_count' => fake()->numberBetween(0, 50),
            'average_satisfaction' => fake()->randomFloat(2, 0, 1),
            'price_multiplier_base' => 100,
        ];
    }
}
