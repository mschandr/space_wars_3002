<?php

namespace Tests\Unit\Services;

use App\DataObjects\PricingContext;
use App\Models\Mineral;
use App\Models\TradingHub;
use App\Models\TradingHubInventory;
use App\Services\Pricing\PricingService;
use App\Services\Trading\HubInventoryMutationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HubInventoryMutationServiceTest extends TestCase
{
    use RefreshDatabase;

    private HubInventoryMutationService $mutationService;
    private PricingService $pricingService;
    private TradingHub $hub;
    private Mineral $mineral;
    private TradingHubInventory $inventory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pricingService = new PricingService();
        $this->mutationService = new HubInventoryMutationService($this->pricingService);

        $this->hub = TradingHub::factory()->create();
        $this->mineral = Mineral::factory()->create(['base_value' => 1000]);
        $this->inventory = TradingHubInventory::factory()->create([
            'trading_hub_id' => $this->hub->id,
            'mineral_id' => $this->mineral->id,
            'quantity' => 1000,
            'demand_level' => 50,
            'supply_level' => 50,
        ]);
        // Load the relationship properly
        $this->inventory = $this->inventory->fresh(['mineral']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_reduces_quantity_on_buy()
    {
        $originalQuantity = $this->inventory->quantity;
        $amount = 100;

        $ctx = new PricingContext(0.08, 1.0, false, 1.0);
        $this->mutationService->applyTrade($this->inventory, $amount, 'buy', $ctx);

        $this->inventory->refresh();
        $this->assertEquals($originalQuantity - $amount, $this->inventory->quantity);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_increases_quantity_on_sell()
    {
        $originalQuantity = $this->inventory->quantity;
        $amount = 100;

        $ctx = new PricingContext(0.08, 1.0, false, 1.0);
        $this->mutationService->applyTrade($this->inventory, $amount, 'sell', $ctx);

        $this->inventory->refresh();
        $this->assertEquals($originalQuantity + $amount, $this->inventory->quantity);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_increases_demand_on_buy()
    {
        $originalDemand = $this->inventory->demand_level;

        $ctx = new PricingContext(0.08, 1.0, false, 1.0);
        $this->mutationService->applyTrade($this->inventory, 100, 'buy', $ctx);

        $this->inventory->refresh();
        $this->assertGreaterThan($originalDemand, $this->inventory->demand_level);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_decreases_supply_on_buy()
    {
        $originalSupply = $this->inventory->supply_level;

        $ctx = new PricingContext(0.08, 1.0, false, 1.0);
        $this->mutationService->applyTrade($this->inventory, 100, 'buy', $ctx);

        $this->inventory->refresh();
        $this->assertLessThan($originalSupply, $this->inventory->supply_level);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_increases_supply_on_sell()
    {
        $originalSupply = $this->inventory->supply_level;

        $ctx = new PricingContext(0.08, 1.0, false, 1.0);
        $this->mutationService->applyTrade($this->inventory, 100, 'sell', $ctx);

        $this->inventory->refresh();
        $this->assertGreaterThan($originalSupply, $this->inventory->supply_level);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_decreases_demand_on_sell()
    {
        $originalDemand = $this->inventory->demand_level;

        $ctx = new PricingContext(0.08, 1.0, false, 1.0);
        $this->mutationService->applyTrade($this->inventory, 100, 'sell', $ctx);

        $this->inventory->refresh();
        $this->assertLessThan($originalDemand, $this->inventory->demand_level);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_persists_mutations_to_database()
    {
        $ctx = new PricingContext(0.08, 1.0, false, 1.0);

        $originalQuantity = $this->inventory->quantity;
        $this->mutationService->applyTrade($this->inventory, 100, 'buy', $ctx);

        // Verify the mutation was persisted to the database
        $reloaded = TradingHubInventory::find($this->inventory->id);
        $this->assertEquals($originalQuantity - 100, $reloaded->quantity);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_clamps_demand_to_100_maximum()
    {
        // Set demand very high
        $this->inventory->update(['demand_level' => 95]);

        $ctx = new PricingContext(0.08, 1.0, false, 1.0);
        $this->mutationService->applyTrade($this->inventory, 1000, 'buy', $ctx);

        $this->inventory->refresh();
        $this->assertLessThanOrEqual(100, $this->inventory->demand_level);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_clamps_supply_to_zero_minimum()
    {
        // Set supply very low
        $this->inventory->update(['supply_level' => 5]);

        $ctx = new PricingContext(0.08, 1.0, false, 1.0);
        $this->mutationService->applyTrade($this->inventory, 1000, 'buy', $ctx);

        $this->inventory->refresh();
        $this->assertGreaterThanOrEqual(0, $this->inventory->supply_level);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_updates_prices_after_mutation()
    {
        $ctx = new PricingContext(0.08, 1.0, false, 1.0);

        $originalBuyPrice = $this->inventory->buy_price;

        $this->mutationService->applyTrade($this->inventory, 100, 'buy', $ctx);

        $this->inventory->refresh();

        // Price should have changed due to supply/demand shift
        // (higher demand and lower supply means higher price)
        $this->assertNotEquals($originalBuyPrice, $this->inventory->buy_price);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_large_trades_with_step_units()
    {
        $ctx = new PricingContext(0.08, 1.0, false, 1.0);

        // Trade units larger than step size
        $unitsPerStep = config('economy.pricing.units_per_step', 10);
        $amount = $unitsPerStep * 15; // 150 units

        $originalDemand = $this->inventory->demand_level;
        $this->mutationService->applyTrade($this->inventory, $amount, 'buy', $ctx);

        $this->inventory->refresh();

        // Demand should have increased by exactly 15 steps
        $expectedDemandIncrease = 15;
        $this->assertEquals($originalDemand + $expectedDemandIncrease, $this->inventory->demand_level);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_small_trades_as_single_step()
    {
        $ctx = new PricingContext(0.08, 1.0, false, 1.0);

        $originalDemand = $this->inventory->demand_level;
        $this->mutationService->applyTrade($this->inventory, 5, 'buy', $ctx); // Less than step size

        $this->inventory->refresh();

        // Should count as 1 step minimum
        $this->assertGreaterThan($originalDemand, $this->inventory->demand_level);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_makes_prices_volatile_through_supply_demand()
    {
        $ctx = new PricingContext(0.08, 1.0, false, 1.0);

        $prices = [];

        // Track price changes through multiple trades
        for ($i = 0; $i < 5; $i++) {
            $this->mutationService->applyTrade($this->inventory, 50, 'buy', $ctx);
            $this->inventory->refresh();
            $prices[] = $this->inventory->buy_price;
        }

        // Prices should be different across trades (not constant)
        $uniquePrices = count(array_unique($prices));
        $this->assertGreaterThan(1, $uniquePrices);
    }
}
