<?php

namespace Database\Factories;

use App\Models\Commodity;
use App\Models\Galaxy;
use App\Models\ResourceDeposit;
use App\Models\TradingHub;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResourceDeposit>
 */
class ResourceDepositFactory extends Factory
{
    protected $model = ResourceDeposit::class;

    public function definition(): array
    {
        return [
            'galaxy_id' => Galaxy::factory(),
            'trading_hub_id' => TradingHub::factory(),
            'commodity_id' => Commodity::factory(),
            'max_extraction_per_tick' => $this->faker->numberBetween(10, 100),
            'quality' => $this->faker->numberBetween(0, 100),
            'total_extracted' => 0,
            'max_total_qty' => $this->faker->numberBetween(5000, 50000),
            'status' => 'ACTIVE',
            'discovered_at' => now(),
        ];
    }
}
