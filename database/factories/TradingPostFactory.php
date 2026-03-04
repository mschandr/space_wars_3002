<?php

namespace Database\Factories;

use App\Models\TradingPost;
use Illuminate\Database\Eloquent\Factories\Factory;

class TradingPostFactory extends Factory
{
    protected $model = TradingPost::class;

    private static array $tradingHubNames = [
        'Kovac\'s Emporium', 'Chen\'s Exchange', 'The Wandering Merchant', 'Port Authority',
        'Starlight Bazaar', 'Void Commerce', 'The Gilded Gate', 'Neutral Ground',
        'The Honest Scale', 'Twilight Trading', 'Fortune\'s Wheel', 'Black Nova Trading',
    ];

    private static array $salvageYardNames = [
        'The Rusty Bolt', 'Salvage Prime', 'The Wreckage Dealer', 'Scrap & Scavenge',
        'Junk Paradise', 'The Bone Yard', 'Iron Recovery', 'The Salvage Master',
    ];

    private static array $shipyardNames = [
        'Titan Yards', 'Nova Shipworks', 'Stellar Construction', 'The Foundry',
        'Apex Drydock', 'Void Engineering', 'The Assembly', 'Pioneer Shipyard',
    ];

    private static array $marketNames = [
        'Central Market', 'The Bazaar', 'Trade Floor', 'Commerce Commons',
        'The Exchange Hub', 'Market Square', 'Trading Post', 'The Fair',
    ];

    public function definition(): array
    {
        $serviceType = $this->faker->randomElement(['trading_hub', 'salvage_yard', 'shipyard', 'market']);
        $name = match ($serviceType) {
            'trading_hub' => fake()->randomElement(self::$tradingHubNames),
            'salvage_yard' => fake()->randomElement(self::$salvageYardNames),
            'shipyard' => fake()->randomElement(self::$shipyardNames),
            'market' => fake()->randomElement(self::$marketNames),
        };

        $baseCriminality = match ($serviceType) {
            'trading_hub' => fake()->randomFloat(2, 0.0, 0.3),
            'salvage_yard' => fake()->randomFloat(2, 0.2, 0.6),  // More shady
            'shipyard' => fake()->randomFloat(2, 0.0, 0.2),     // Legitimate
            'market' => fake()->randomFloat(2, 0.1, 0.4),
        };

        return [
            'uuid' => fake()->uuid(),
            'name' => $name,
            'service_type' => $serviceType,
            'base_criminality' => $baseCriminality,
            'personality' => [
                'honesty' => fake()->randomFloat(2, 0.1, 1.0),
                'greed' => fake()->randomFloat(2, 0.1, 1.0),
                'charm' => fake()->randomFloat(2, 0.1, 1.0),
                'risk_tolerance' => fake()->randomFloat(2, 0.1, 1.0),
            ],
            'dialogue_pool' => [
                'greeting' => [
                    "Welcome to {$name}.",
                    "Looking for something?",
                    "Come in, don't be shy.",
                ],
                'deal_accepted' => [
                    "Pleasure doing business.",
                    "Great choice.",
                    "Thank you for your patronage.",
                ],
                'farewell' => [
                    "Come again soon.",
                    "Safe travels.",
                    "Until next time.",
                ],
            ],
            'markup_base' => match ($serviceType) {
                'trading_hub' => fake()->randomFloat(4, -0.05, 0.15),
                'salvage_yard' => fake()->randomFloat(4, 0.20, 0.40),  // Higher margins
                'shipyard' => fake()->randomFloat(4, 0.10, 0.25),
                'market' => fake()->randomFloat(4, 0.05, 0.20),
            },
        ];
    }

    /**
     * Create a black market trading post
     */
    public function blackMarket(): self
    {
        return $this->state(function (array $attributes) {
            $serviceType = fake()->randomElement(['salvage_yard', 'market']);
            $name = match ($serviceType) {
                'salvage_yard' => fake()->randomElement(self::$salvageYardNames),
                'market' => fake()->randomElement(self::$marketNames),
                default => 'Black Market',
            };
            $markup = match ($serviceType) {
                'salvage_yard' => fake()->randomFloat(4, 0.20, 0.40),
                'market' => fake()->randomFloat(4, 0.05, 0.20),
                default => 0.15,
            };

            return [
                'base_criminality' => fake()->randomFloat(2, 0.85, 1.0),
                'service_type' => $serviceType,
                'name' => $name,
                'markup_base' => $markup,
            ];
        });
    }
}
