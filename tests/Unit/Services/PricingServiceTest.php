<?php

namespace Tests\Unit\Services;

use App\DataObjects\PricingContext;
use App\Models\Mineral;
use App\Models\TradingHub;
use App\Models\TradingHubInventory;
use App\Services\Pricing\PricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PricingServiceTest extends TestCase
{
    use RefreshDatabase;

    private PricingService $pricingService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pricingService = new PricingService();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_computes_price_deterministically()
    {
        $mineral = Mineral::factory()->create(['base_value' => 1000]);
        $hub = TradingHub::factory()->create();
        $inventory = TradingHubInventory::factory()->create([
            'trading_hub_id' => $hub->id,
            'mineral_id' => $mineral->id,
            'demand_level' => 50,
            'supply_level' => 50,
        ]);
        $inventory->mineral = $mineral;

        $ctx = new PricingContext(
            spread: 0.08,
            eventMultiplier: 1.0,
            isMirrorUniverse: false,
            mirrorBoost: 1.0
        );

        $price1 = $this->pricingService->computePrice($inventory, $ctx);
        $price2 = $this->pricingService->computePrice($inventory, $ctx);

        $this->assertEquals($price1, $price2);
        $this->assertIsInt($price1);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_applies_spread_correctly()
    {
        $mineral = Mineral::factory()->create(['base_value' => 1000]);
        $hub = TradingHub::factory()->create();
        $inventory = TradingHubInventory::factory()->create([
            'trading_hub_id' => $hub->id,
            'mineral_id' => $mineral->id,
            'demand_level' => 50,
            'supply_level' => 50,
        ]);
        $inventory->mineral = $mineral;

        $ctx = new PricingContext(
            spread: 0.10,
            eventMultiplier: 1.0,
            isMirrorUniverse: false,
            mirrorBoost: 1.0
        );

        [$buyPrice, $sellPrice] = $this->pricingService->computeBuySellPrices($inventory, $ctx);
        $midPrice = $this->pricingService->computePrice($inventory, $ctx);

        // Buy price should be lower (minus spread)
        $this->assertLessThan($midPrice, $buyPrice);
        // Sell price should be higher (plus spread)
        $this->assertGreaterThan($midPrice, $sellPrice);

        // Verify spread application
        $expectedBuy = (int) round($midPrice * (1 - 0.10));
        $expectedSell = (int) round($midPrice * (1 + 0.10));

        $this->assertEquals($expectedBuy, $buyPrice);
        $this->assertEquals($expectedSell, $sellPrice);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_clamps_price_to_min_multiplier()
    {
        $mineral = Mineral::factory()->create(['base_value' => 1000]);
        $hub = TradingHub::factory()->create();
        $inventory = TradingHubInventory::factory()->create([
            'trading_hub_id' => $hub->id,
            'mineral_id' => $mineral->id,
            'demand_level' => 0,  // Maximum undersupply
            'supply_level' => 100, // Maximum oversupply
        ]);
        $inventory->mineral = $mineral;

        $ctx = new PricingContext(
            spread: 0.08,
            eventMultiplier: 1.0,
            isMirrorUniverse: false,
            mirrorBoost: 1.0
        );

        $price = $this->pricingService->computePrice($inventory, $ctx);
        $minPrice = $mineral->base_value * config('economy.pricing.min_multiplier', 0.10);

        $this->assertGreaterThanOrEqual($minPrice, $price);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_clamps_price_to_max_multiplier()
    {
        $mineral = Mineral::factory()->create(['base_value' => 1000]);
        $hub = TradingHub::factory()->create();
        $inventory = TradingHubInventory::factory()->create([
            'trading_hub_id' => $hub->id,
            'mineral_id' => $mineral->id,
            'demand_level' => 100, // Maximum demand
            'supply_level' => 0,   // Zero supply
        ]);
        $inventory->mineral = $mineral;

        $ctx = new PricingContext(
            spread: 0.08,
            eventMultiplier: 1.0,
            isMirrorUniverse: false,
            mirrorBoost: 1.0
        );

        $price = $this->pricingService->computePrice($inventory, $ctx);
        $maxPrice = $mineral->base_value * config('economy.pricing.max_multiplier', 10.00);

        $this->assertLessThanOrEqual($maxPrice, $price);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_applies_event_multiplier()
    {
        $mineral = Mineral::factory()->create(['base_value' => 1000]);
        $hub = TradingHub::factory()->create();
        $inventory = TradingHubInventory::factory()->create([
            'trading_hub_id' => $hub->id,
            'mineral_id' => $mineral->id,
            'demand_level' => 50,
            'supply_level' => 50,
        ]);
        $inventory->mineral = $mineral;

        $ctxNormal = new PricingContext(
            spread: 0.08,
            eventMultiplier: 1.0,
            isMirrorUniverse: false,
            mirrorBoost: 1.0
        );

        $ctxEventBoosted = new PricingContext(
            spread: 0.08,
            eventMultiplier: 1.5, // 50% price increase from event
            isMirrorUniverse: false,
            mirrorBoost: 1.0
        );

        $normalPrice = $this->pricingService->computePrice($inventory, $ctxNormal);
        $boostedPrice = $this->pricingService->computePrice($inventory, $ctxEventBoosted);

        $this->assertGreaterThan($normalPrice, $boostedPrice);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_applies_mirror_universe_boost()
    {
        $mineral = Mineral::factory()->create(['base_value' => 1000]);
        $hub = TradingHub::factory()->create();
        $inventory = TradingHubInventory::factory()->create([
            'trading_hub_id' => $hub->id,
            'mineral_id' => $mineral->id,
            'demand_level' => 50,
            'supply_level' => 50,
        ]);
        $inventory->mineral = $mineral;

        $ctxNormal = new PricingContext(
            spread: 0.08,
            eventMultiplier: 1.0,
            isMirrorUniverse: false,
            mirrorBoost: 1.0
        );

        $ctxMirror = new PricingContext(
            spread: 0.08,
            eventMultiplier: 1.0,
            isMirrorUniverse: true,
            mirrorBoost: 1.5 // 50% higher in mirror universe
        );

        $normalPrice = $this->pricingService->computePrice($inventory, $ctxNormal);
        $mirrorPrice = $this->pricingService->computePrice($inventory, $ctxMirror);

        $this->assertGreaterThan($normalPrice, $mirrorPrice);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_integer_prices()
    {
        $mineral = Mineral::factory()->create(['base_value' => 999]);
        $hub = TradingHub::factory()->create();
        $inventory = TradingHubInventory::factory()->create([
            'trading_hub_id' => $hub->id,
            'mineral_id' => $mineral->id,
            'demand_level' => 33,
            'supply_level' => 67,
        ]);
        $inventory->mineral = $mineral;

        $ctx = new PricingContext(
            spread: 0.08,
            eventMultiplier: 1.0,
            isMirrorUniverse: false,
            mirrorBoost: 1.0
        );

        $price = $this->pricingService->computePrice($inventory, $ctx);
        [$buyPrice, $sellPrice] = $this->pricingService->computeBuySellPrices($inventory, $ctx);

        $this->assertIsInt($price);
        $this->assertIsInt($buyPrice);
        $this->assertIsInt($sellPrice);
    }
}
