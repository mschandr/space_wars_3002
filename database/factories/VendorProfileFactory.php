<?php

namespace Database\Factories;

use App\Enums\Vendor\VendorArchetype;
use App\Models\VendorProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\VendorProfile>
 */
class VendorProfileFactory extends Factory
{
    protected $model = VendorProfile::class;

    private static array $vendorNames = [
        'Kovac\'s Trading Post',
        'The Wandering Merchant',
        'Starlight Bazaar',
        'Port Authority Exchange',
        'Chen\'s Emporium',
        'Black Nova Trading',
        'The Silent Broker',
        'Fortuna\'s Wheel',
        'The Rusty Bolt',
        'Void Commerce Ltd',
        'The Gilded Gate',
        'Crimson Tide Supplies',
        'Neutral Ground',
        'The Honest Scale',
        'Twilight Trading Company',
        'The Phantom Exchange',
    ];


    public function definition(): array
    {
        $archetype = VendorArchetype::cases()[array_rand(VendorArchetype::cases())];

        return [
            'uuid' => fake()->uuid(),
            'name' => fake()->randomElement(self::$vendorNames),
            'archetype' => $archetype->value,
            'service_type' => fake()->randomElement(['trading_hub', 'salvage_yard', 'shipyard', 'market']),
            'criminality' => fake()->randomFloat(2, 0.0, 1.0),
            'personality' => [
                'honesty' => fake()->randomFloat(2, 0.1, 1.0),
                'greed' => fake()->randomFloat(2, 0.1, 1.0),
                'risk_tolerance' => fake()->randomFloat(2, 0.1, 1.0),
                'charm' => fake()->randomFloat(2, 0.1, 1.0),
                'ego_drive' => fake()->randomFloat(2, 0.1, 1.0),
                'empathy' => fake()->randomFloat(2, 0.1, 1.0),
                'curiosity' => fake()->randomFloat(2, 0.1, 1.0),
            ],
            'markup_base' => $archetype->baseMarkup(),
        ];
    }

}
