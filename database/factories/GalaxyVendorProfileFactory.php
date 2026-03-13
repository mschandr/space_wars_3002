<?php

namespace Database\Factories;

use App\Models\Galaxy;
use App\Models\GalaxyVendorProfile;
use App\Models\PointOfInterest;
use App\Models\TradingPost;
use App\Models\VendorProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\GalaxyVendorProfile>
 */
class GalaxyVendorProfileFactory extends Factory
{
    protected $model = GalaxyVendorProfile::class;

    public function definition(): array
    {
        return [
            'uuid' => fake()->uuid(),
            'galaxy_id' => Galaxy::inRandomOrder()->first()?->id ?? Galaxy::factory(),
            'poi_id' => PointOfInterest::inRandomOrder()->first()?->id ?? PointOfInterest::factory(),
            'vendor_profile_id' => VendorProfile::inRandomOrder()->first()?->id ?? VendorProfile::factory(),
            'trading_post_id' => TradingPost::inRandomOrder()->first()?->id,
            'service_type' => fake()->randomElement(['trading_hub', 'salvage_yard', 'shipyard', 'market']),
            'criminality' => fake()->randomFloat(2, 0.0, 1.0),
            'dialogue_generation_status' => 'pending',
            'dialogue_generation_version' => 1,
            'dialogue_generated_at' => null,
        ];
    }
}
