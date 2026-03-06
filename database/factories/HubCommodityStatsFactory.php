<?php

namespace Database\Factories;

use App\Models\Commodity;
use App\Models\HubCommodityStats;
use App\Models\TradingHub;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HubCommodityStats>
 */
class HubCommodityStatsFactory extends Factory
{
    protected $model = HubCommodityStats::class;

    public function definition(): array
    {
        return [
            'trading_hub_id' => TradingHub::factory(),
            'commodity_id' => Commodity::factory(),
            'avg_daily_demand' => $this->faker->numberBetween(10, 1000),
            'avg_daily_supply' => $this->faker->numberBetween(10, 1000),
            'cached_buy_price' => null,
            'cached_sell_price' => null,
            'last_computed_at' => now(),
        ];
    }
}
