<?php

namespace Database\Factories;

use App\Models\CustomsOfficial;
use App\Models\Galaxy;
use App\Models\GalaxyCustomsRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\GalaxyCustomsRecord>
 */
class GalaxyCustomsRecordFactory extends Factory
{
    protected $model = GalaxyCustomsRecord::class;

    public function definition(): array
    {
        return [
            'uuid' => fake()->uuid(),
            'galaxy_id' => Galaxy::inRandomOrder()->first()?->id ?? Galaxy::factory(),
            'customs_official_id' => CustomsOfficial::inRandomOrder()->first()?->id ?? CustomsOfficial::factory(),
            'total_checks' => fake()->numberBetween(0, 100),
            'times_fined' => fake()->numberBetween(0, 20),
            'times_bribed' => fake()->numberBetween(0, 10),
            'total_bribes_paid' => fake()->numberBetween(0, 50000),
            'actual_honesty' => null,  // Starts null, derived from template
            'relationship_score' => fake()->numberBetween(-50, 50),
        ];
    }
}
