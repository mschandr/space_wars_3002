<?php

namespace Database\Factories;

use App\Models\Galaxy;
use App\Models\Player;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Player>
 */
class PlayerFactory extends Factory
{
    protected $model = Player::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => Str::uuid(),
            'user_id' => User::factory(),
            'galaxy_id' => Galaxy::factory(),
            'call_sign' => fake()->unique()->userName(),
            'credits' => 1000.00,
            'experience' => 0,
            'level' => 1,
            'current_poi_id' => null,
            'status' => 'active',
        ];
    }

    /**
     * Set specific experience and calculate level automatically
     */
    public function withExperience(int $experience): static
    {
        $level = (int) floor(sqrt($experience / 100)) + 1;

        return $this->state(fn (array $attributes) => [
            'experience' => $experience,
            'level' => $level,
        ]);
    }

    /**
     * Set specific level (calculates minimum XP for that level)
     */
    public function atLevel(int $level): static
    {
        // Calculate minimum XP needed for this level
        // Level = floor(sqrt(XP / 100)) + 1
        // Solving for XP: XP = ((Level - 1)^2) * 100
        $minXP = pow($level - 1, 2) * 100;

        return $this->state(fn (array $attributes) => [
            'experience' => $minXP,
            'level' => $level,
        ]);
    }

    /**
     * Rich player with lots of credits
     */
    public function rich(float $credits = 1000000.00): static
    {
        return $this->state(fn (array $attributes) => [
            'credits' => $credits,
        ]);
    }

    /**
     * Broke player with no credits
     */
    public function broke(): static
    {
        return $this->state(fn (array $attributes) => [
            'credits' => 0.00,
        ]);
    }

    /**
     * Veteran player (high level)
     */
    public function veteran(): static
    {
        return $this->atLevel(10)->rich(50000.00);
    }
}
