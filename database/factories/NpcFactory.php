<?php

namespace Database\Factories;

use App\Models\Galaxy;
use App\Models\Npc;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Npc>
 */
class NpcFactory extends Factory
{
    protected $model = Npc::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $archetype = fake()->randomElement(array_keys(Npc::ARCHETYPES));
        $archetypeConfig = Npc::ARCHETYPES[$archetype];

        return [
            'uuid' => Str::uuid(),
            'galaxy_id' => Galaxy::factory(),
            'call_sign' => fake()->unique()->userName().'-NPC',
            'archetype' => $archetype,
            'credits' => fake()->randomFloat(2, 5000, 50000),
            'experience' => 0,
            'level' => 1,
            'difficulty' => 'medium',
            'aggression' => $archetypeConfig['aggression'] + fake()->randomFloat(2, -0.1, 0.1),
            'risk_tolerance' => $archetypeConfig['risk_tolerance'] + fake()->randomFloat(2, -0.1, 0.1),
            'trade_focus' => $archetypeConfig['trade_focus'] + fake()->randomFloat(2, -0.1, 0.1),
            'personality' => null,
            'status' => 'active',
            'current_activity' => 'idle',
            'current_poi_id' => null,
        ];
    }

    /**
     * Create a trader NPC
     */
    public function trader(): static
    {
        return $this->state(fn (array $attributes) => [
            'archetype' => 'trader',
            'aggression' => 0.1,
            'risk_tolerance' => 0.3,
            'trade_focus' => 0.9,
        ]);
    }

    /**
     * Create an explorer NPC
     */
    public function explorer(): static
    {
        return $this->state(fn (array $attributes) => [
            'archetype' => 'explorer',
            'aggression' => 0.2,
            'risk_tolerance' => 0.7,
            'trade_focus' => 0.4,
        ]);
    }

    /**
     * Create a pirate hunter NPC
     */
    public function pirateHunter(): static
    {
        return $this->state(fn (array $attributes) => [
            'archetype' => 'pirate_hunter',
            'aggression' => 0.8,
            'risk_tolerance' => 0.6,
            'trade_focus' => 0.2,
        ]);
    }

    /**
     * Create a miner NPC
     */
    public function miner(): static
    {
        return $this->state(fn (array $attributes) => [
            'archetype' => 'miner',
            'aggression' => 0.1,
            'risk_tolerance' => 0.4,
            'trade_focus' => 0.6,
        ]);
    }

    /**
     * Create a merchant NPC
     */
    public function merchant(): static
    {
        return $this->state(fn (array $attributes) => [
            'archetype' => 'merchant',
            'aggression' => 0.05,
            'risk_tolerance' => 0.2,
            'trade_focus' => 0.95,
            'credits' => fake()->randomFloat(2, 50000, 200000),
        ]);
    }

    /**
     * Set difficulty level
     */
    public function difficulty(string $difficulty): static
    {
        $multiplier = Npc::DIFFICULTY_MULTIPLIERS[$difficulty]['credits'] ?? 1.0;

        return $this->state(fn (array $attributes) => [
            'difficulty' => $difficulty,
            'credits' => $attributes['credits'] * $multiplier,
        ]);
    }

    /**
     * Easy difficulty
     */
    public function easy(): static
    {
        return $this->difficulty('easy');
    }

    /**
     * Hard difficulty
     */
    public function hard(): static
    {
        return $this->difficulty('hard');
    }

    /**
     * Expert difficulty
     */
    public function expert(): static
    {
        return $this->difficulty('expert');
    }

    /**
     * Veteran NPC with high level
     */
    public function veteran(): static
    {
        $experience = pow(9, 2) * 100; // Level 10

        return $this->state(fn (array $attributes) => [
            'experience' => $experience,
            'level' => 10,
            'credits' => fake()->randomFloat(2, 100000, 500000),
        ]);
    }
}
