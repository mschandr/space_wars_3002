<?php

namespace Database\Factories;

use App\Enums\Vendor\VendorArchetype;
use App\Models\Galaxy;
use App\Models\PointOfInterest;
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

    private static array $dialoguePools = [
        'honest_broker' => [
            'greeting' => [
                'Welcome! We offer fair prices for everything.',
                'Greetings. Let me show you what we have.',
                'Come in, come in. You\'ll find good deals here.',
            ],
            'deal_accepted' => [
                'A pleasure doing business with you.',
                'Excellent choice. Fair and square.',
                'I appreciate your patronage.',
            ],
        ],
        'hard_bargainer' => [
            'greeting' => [
                'Looking for a deal, eh? I got what you need.',
                'You come to the right place. Everything must go.',
                'Welcome back... or first time?',
            ],
            'deal_accepted' => [
                'Good business today. Come again?',
                'You got yourself a bargain there.',
                'Not bad, not bad. We\'ll do business again.',
            ],
        ],
        'fence' => [
            'greeting' => [
                'No questions asked, no names either.',
                'I deal in... specialty items.',
                'You look like someone who appreciates discretion.',
            ],
            'deal_accepted' => [
                'Pleasure doing discrete business.',
                'You understand the value of silence.',
                'This never happened.',
            ],
        ],
    ];

    public function definition(): array
    {
        $archetype = VendorArchetype::cases()[array_rand(VendorArchetype::cases())];

        return [
            'uuid' => fake()->uuid(),
            'galaxy_id' => Galaxy::inRandomOrder()->first()?->id ?? Galaxy::factory(),
            'name' => fake()->randomElement(self::$vendorNames),
            'archetype' => $archetype,
            'poi_id' => PointOfInterest::inRandomOrder()->first()?->id ?? PointOfInterest::factory(),
            'personality' => [
                'honesty' => fake()->randomFloat(2, 0.1, 1.0),
                'greed' => fake()->randomFloat(2, 0.1, 1.0),
                'risk_tolerance' => fake()->randomFloat(2, 0.1, 1.0),
                'charm' => fake()->randomFloat(2, 0.1, 1.0),
            ],
            'dialogue_pool' => $this->generateDialoguePool($archetype),
            'markup_base' => $archetype->baseMarkup(),
        ];
    }

    /**
     * Generate dialogue pool for an archetype
     */
    private function generateDialoguePool(VendorArchetype $archetype): array
    {
        // Return predefined pools for some archetypes, generic for others
        $archetypeKey = $archetype->value;

        if (isset(self::$dialoguePools[$archetypeKey])) {
            return self::$dialoguePools[$archetypeKey];
        }

        // Generic dialogue pool
        return [
            'greeting' => [
                'Welcome to my shop.',
                'Looking for something?',
                'Come in, don\'t be shy.',
            ],
            'deal_accepted' => [
                'Great choice.',
                'Pleasure doing business.',
                'Thank you for your patronage.',
            ],
            'farewell' => [
                'Come again soon.',
                'Safe travels.',
                'Until next time.',
            ],
        ];
    }
}
