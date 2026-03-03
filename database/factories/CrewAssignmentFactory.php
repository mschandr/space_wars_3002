<?php

namespace Database\Factories;

use App\Models\CrewAssignment;
use App\Models\CrewMember;
use App\Models\Galaxy;
use App\Models\TradingHub;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CrewAssignment>
 */
class CrewAssignmentFactory extends Factory
{
    protected $model = CrewAssignment::class;

    public function definition(): array
    {
        return [
            'uuid' => fake()->uuid(),
            'galaxy_id' => Galaxy::inRandomOrder()->first()?->id ?? Galaxy::factory(),
            'crew_member_id' => CrewMember::inRandomOrder()->first()?->id ?? CrewMember::factory(),
            'trading_hub_id' => TradingHub::inRandomOrder()->first()?->id ?? TradingHub::factory(),
        ];
    }
}
