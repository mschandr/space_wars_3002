<?php

namespace Database\Factories;

use App\Enums\Crew\CrewAlignment;
use App\Enums\Crew\CrewRole;
use App\Models\CrewMember;
use App\Models\Galaxy;
use App\Models\PointOfInterest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CrewMember>
 */
class CrewMemberFactory extends Factory
{
    protected $model = CrewMember::class;

    private static array $firstNames = [
        'James', 'Sarah', 'Marcus', 'Elena', 'David', 'Luna', 'Alex', 'Iris',
        'Viktor', 'Zara', 'Kai', 'Nova', 'Ethan', 'Sienna', 'Andor', 'Mira',
    ];

    private static array $lastNames = [
        'Kovacs', 'Torres', 'Chen', 'O\'Brien', 'Volkov', 'Sharma', 'Hansen', 'Rossi',
        'Nakamura', 'Petrov', 'Adelstein', 'Okafor', 'Dubois', 'Wagner', 'Khan', 'Lee',
    ];

    private static array $backStories = [
        'Former military officer seeking redemption in the stars.',
        'Brilliant engineer who left corporate life behind.',
        'Trader with connections in every spaceport.',
        'Explorer driven by curiosity about unknown worlds.',
        'Survivor of a mining colony disaster.',
        'Aristocrat on the run from an uncomfortable future.',
        'Self-taught hacker with a mysterious past.',
        'Diplomatic envoy between warring factions.',
        'Freelancer with a talent for getting things done.',
        'Lost soul searching for meaning among the stars.',
    ];

    public function definition(): array
    {
        $role = CrewRole::cases()[array_rand(CrewRole::cases())];

        // Distribution: 40% lawful, 40% neutral, 20% shady
        $rand = random_int(0, 100);
        if ($rand < 40) {
            $alignment = CrewAlignment::LAWFUL;
        } elseif ($rand < 80) {
            $alignment = CrewAlignment::NEUTRAL;
        } else {
            $alignment = CrewAlignment::SHADY;
        }

        return [
            'uuid' => fake()->uuid(),
            'galaxy_id' => Galaxy::inRandomOrder()->first()?->id ?? Galaxy::factory(),
            'name' => fake()->firstName() . ' ' . fake()->lastName(),
            'role' => $role,
            'alignment' => $alignment,
            'player_ship_id' => null, // Available for hire by default
            'current_poi_id' => PointOfInterest::inRandomOrder()->first()?->id ?? PointOfInterest::factory(),
            'shady_actions' => 0,
            'reputation' => random_int(-50, 50),
            'traits' => [
                'negotiation' => fake()->randomFloat(2, 0.1, 1.0),
                'intimidation' => fake()->randomFloat(2, 0.1, 1.0),
                'charm' => fake()->randomFloat(2, 0.1, 1.0),
                'technical_skill' => fake()->randomFloat(2, 0.1, 1.0),
            ],
            'backstory' => fake()->randomElement(self::$backStories),
        ];
    }
}
