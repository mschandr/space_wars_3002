<?php

namespace Database\Factories;

use App\Models\CustomsOfficial;
use App\Models\PointOfInterest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CustomsOfficial>
 */
class CustomsOfficialFactory extends Factory
{
    protected $model = CustomsOfficial::class;

    private static array $officialNames = [
        'Captain Volkov',
        'Inspector Chen',
        'Officer Torres',
        'Commandant Le Blanc',
        'Marshal Petrov',
        'Captain Okafor',
        'Inspector Nakamura',
        'Officer Rodriguez',
        'Captain Adelstein',
        'Marshal Kowalski',
    ];

    public function definition(): array
    {
        return [
            'uuid' => fake()->uuid(),
            'poi_id' => PointOfInterest::inRandomOrder()->first()?->id ?? PointOfInterest::factory(),
            'name' => fake()->randomElement(self::$officialNames),
            'honesty' => fake()->randomFloat(2, 0.1, 1.0),      // 0=corrupt, 1=incorruptible
            'severity' => fake()->randomFloat(2, 0.1, 1.0),     // 0=lenient, 1=very strict
            'bribe_threshold' => random_int(500, 5000),         // Minimum bribe amount
            'detection_skill' => fake()->randomFloat(2, 0.3, 0.95),  // 30-95% detection chance
        ];
    }

    /**
     * Create a very honest official (hard to bribe)
     */
    public function honest(): self
    {
        return $this->state([
            'honesty' => fake()->randomFloat(2, 0.8, 1.0),
            'severity' => fake()->randomFloat(2, 0.6, 0.9),
            'detection_skill' => fake()->randomFloat(2, 0.7, 0.95),
        ]);
    }

    /**
     * Create a corrupt official (easy to bribe)
     */
    public function corrupt(): self
    {
        return $this->state([
            'honesty' => fake()->randomFloat(2, 0.0, 0.4),
            'severity' => fake()->randomFloat(2, 0.1, 0.5),
            'bribe_threshold' => random_int(100, 1000),
            'detection_skill' => fake()->randomFloat(2, 0.2, 0.6),
        ]);
    }
}
