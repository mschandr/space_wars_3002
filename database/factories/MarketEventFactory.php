<?php

namespace Database\Factories;

use App\Enums\MarketEventType;
use App\Models\MarketEvent;
use App\Models\Mineral;
use App\Models\TradingHub;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MarketEvent>
 */
class MarketEventFactory extends Factory
{
    protected $model = MarketEvent::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $eventType = MarketEventType::random();
        $multiplier = $eventType->getRandomMultiplier();

        return [
            'uuid' => Str::uuid(),
            'mineral_id' => Mineral::factory(),
            'trading_hub_id' => TradingHub::factory(),
            'event_type' => $eventType,
            'price_multiplier' => $multiplier,
            'description' => fake()->sentence(),
            'started_at' => now()->subHour(),
            'expires_at' => now()->addHours(fake()->numberBetween(1, 24)),
            'is_active' => true,
        ];
    }

    /**
     * Active event
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'started_at' => now()->subHour(),
            'expires_at' => now()->addHours(2),
            'is_active' => true,
        ]);
    }

    /**
     * Expired event
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'started_at' => now()->subHours(3),
            'expires_at' => now()->subHour(),
            'is_active' => true,
        ]);
    }

    /**
     * Inactive event
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Global event (affects all minerals)
     */
    public function global(): static
    {
        return $this->state(fn (array $attributes) => [
            'mineral_id' => null,
        ]);
    }

    /**
     * Galaxy-wide event (affects all hubs)
     */
    public function galaxyWide(): static
    {
        return $this->state(fn (array $attributes) => [
            'trading_hub_id' => null,
        ]);
    }

    /**
     * Supply shortage event (price increase)
     */
    public function supplyShortage(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_type' => MarketEventType::SUPPLY_SHORTAGE,
            'price_multiplier' => fake()->randomFloat(2, 2.0, 3.0),
        ]);
    }

    /**
     * Market flooding event (price decrease)
     */
    public function marketFlooding(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_type' => MarketEventType::MARKET_FLOODING,
            'price_multiplier' => fake()->randomFloat(2, 0.3, 0.5),
        ]);
    }

    /**
     * Demand spike event
     */
    public function demandSpike(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_type' => MarketEventType::DEMAND_SPIKE,
            'price_multiplier' => fake()->randomFloat(2, 2.0, 2.5),
        ]);
    }
}
