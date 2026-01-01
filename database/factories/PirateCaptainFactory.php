<?php

namespace Database\Factories;

use App\Models\PirateCaptain;
use App\Models\PirateFaction;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PirateCaptain>
 */
class PirateCaptainFactory extends Factory
{
    protected $model = PirateCaptain::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $firstNames = ['Vex', 'Kira', 'Drax', 'Zara', 'Thane', 'Nyx', 'Kor', 'Lyra', 'Sable', 'Raze'];
        $lastNames = ['Blackthorne', 'Steelclaw', 'Ironfist', 'Darkwater', 'Shadowbane', 'Bloodfang', 'Voidreaver', 'Stormborn'];
        $titles = ['Captain', 'Commander', 'Admiral', 'Warlord', 'Commodore'];

        return [
            'uuid' => Str::uuid(),
            'faction_id' => PirateFaction::factory(), // Create faction automatically
            'first_name' => fake()->randomElement($firstNames),
            'last_name' => fake()->randomElement($lastNames),
            'title' => fake()->randomElement($titles),
            'combat_skill' => fake()->numberBetween(40, 90),
            'attributes' => null,
        ];
    }
}
