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
     * Provide default attribute values for a new Npc model instance.
     *
     * @return array<string, mixed> Associative array mapping Npc model attributes to their default values.
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
     * Configure the factory to produce a trader archetype NPC.
     *
     * Sets the archetype to 'trader' and applies trader-specific attributes:
     * aggression = 0.1, risk_tolerance = 0.3, trade_focus = 0.9.
     *
     * @return static The factory instance with the trader state applied.
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
     * Configure the factory to produce an explorer NPC with archetype 'explorer' and tuned attributes.
     *
     * @return static The factory state producing an explorer NPC with aggression 0.2, risk_tolerance 0.7, and trade_focus 0.4.
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
     * Configure the factory to produce a pirate hunter NPC.
     *
     * Sets the archetype to "pirate_hunter" and assigns aggression, risk tolerance,
     * and trade focus values appropriate for a pirate hunter.
     *
     * @return static The factory instance with the pirate hunter state applied.
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
     * Configure the factory to produce a miner NPC.
     *
     * @return static The factory instance with archetype set to "miner" and aggression, risk_tolerance, and trade_focus adjusted for a miner. 
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
     * Configure the factory to produce a merchant NPC.
     *
     * @return static Factory instance with attributes set for a merchant NPC:
     *                `archetype` = 'merchant', `aggression` = 0.05,
     *                `risk_tolerance` = 0.2, `trade_focus` = 0.95,
     *                and `credits` randomized in a higher range.
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
         * Set the NPC difficulty and scale its credits by the configured difficulty multiplier.
         *
         * Looks up the credits multiplier from Npc::DIFFICULTY_MULTIPLIERS[$difficulty]['credits'] and falls back to 1.0 if not found.
         *
         * @param string $difficulty The difficulty key to apply (e.g., 'easy', 'hard', 'expert').
         * @return static The factory state with 'difficulty' set and 'credits' multiplied accordingly.
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
     * Set the factory's difficulty to "easy".
     *
     * @return static The factory configured for easy difficulty.
     */
    public function easy(): static
    {
        return $this->difficulty('easy');
    }

    /**
     * Configure the factory to produce NPCs with 'hard' difficulty.
     *
     * @return static The factory instance with hard difficulty applied.
     */
    public function hard(): static
    {
        return $this->difficulty('hard');
    }

    /**
     * Configure the factory to produce NPCs with expert difficulty.
     *
     * Applies the 'expert' difficulty setting and adjusts credits according to its multiplier.
     *
     * @return static The factory instance with expert difficulty applied.
     */
    public function expert(): static
    {
        return $this->difficulty('expert');
    }

    /**
     * Configure the factory to produce a veteran NPC with elevated stats.
     *
     * Sets the NPC to level 10, experience to 8100, and credits to a random value between 100000 and 500000.
     *
     * @return static The factory configured to create a veteran NPC (level 10, experience 8100, boosted credits).
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