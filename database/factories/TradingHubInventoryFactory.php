<?php

namespace Database\Factories;

use App\Models\Mineral;
use App\Models\TradingHub;
use App\Models\TradingHubInventory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TradingHubInventory>
 */
class TradingHubInventoryFactory extends Factory
{
    protected $model = TradingHubInventory::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $basePrice = fake()->randomFloat(2, 10, 200);

        return [
            'trading_hub_id' => TradingHub::factory(),
            'mineral_id' => Mineral::factory(),
            'quantity' => fake()->numberBetween(100, 10000),
            'current_price' => $basePrice,
            'buy_price' => $basePrice * 0.85, // Hub buys at 85% of base
            'sell_price' => $basePrice * 1.15, // Hub sells at 115% of base
            'demand_level' => 50,
            'supply_level' => 50,
            'last_price_update' => now(),
        ];
    }

    /**
     * High stock
     */
    public function highStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity' => fake()->numberBetween(50000, 100000),
        ]);
    }

    /**
     * Low stock
     */
    public function lowStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity' => fake()->numberBetween(1, 100),
        ]);
    }

    /**
     * Expensive prices
     */
    public function expensive(): static
    {
        return $this->state(fn (array $attributes) => [
            'buy_price' => 150.00,
            'sell_price' => 200.00,
        ]);
    }

    /**
     * Cheap prices
     */
    public function cheap(): static
    {
        return $this->state(fn (array $attributes) => [
            'buy_price' => 5.00,
            'sell_price' => 10.00,
        ]);
    }
}
