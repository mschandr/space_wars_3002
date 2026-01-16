<?php

namespace Database\Factories;

use App\Models\Colony;
use App\Models\Player;
use App\Models\PlayerNotification;
use App\Models\PointOfInterest;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PlayerNotification>
 */
class PlayerNotificationFactory extends Factory
{
    protected $model = PlayerNotification::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => Str::uuid(),
            'player_id' => Player::factory(),
            'type' => fake()->randomElement(['colony_event', 'combat', 'trade', 'exploration', 'achievement']),
            'severity' => fake()->randomElement(['info', 'warning', 'critical']),
            'title' => fake()->sentence(4),
            'message' => fake()->sentence(10),
            'colony_id' => null,
            'poi_id' => null,
            'data' => null,
            'is_read' => false,
            'read_at' => null,
        ];
    }

    /**
     * Indicate that the notification is unread.
     */
    public function unread(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_read' => false,
            'read_at' => null,
        ]);
    }

    /**
     * Indicate that the notification is read.
     */
    public function read(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_read' => true,
            'read_at' => now(),
        ]);
    }

    /**
     * Set notification severity to critical.
     */
    public function critical(): static
    {
        return $this->state(fn (array $attributes) => [
            'severity' => 'critical',
        ]);
    }

    /**
     * Set notification severity to warning.
     */
    public function warning(): static
    {
        return $this->state(fn (array $attributes) => [
            'severity' => 'warning',
        ]);
    }

    /**
     * Set notification severity to info.
     */
    public function info(): static
    {
        return $this->state(fn (array $attributes) => [
            'severity' => 'info',
        ]);
    }

    /**
     * Associate with a colony.
     */
    public function forColony(): static
    {
        return $this->state(fn (array $attributes) => [
            'colony_id' => Colony::factory(),
            'type' => 'colony_event',
        ]);
    }

    /**
     * Associate with a POI.
     */
    public function forPoi(): static
    {
        return $this->state(fn (array $attributes) => [
            'poi_id' => PointOfInterest::factory(),
            'type' => 'exploration',
        ]);
    }
}
