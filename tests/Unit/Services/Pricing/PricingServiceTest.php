<?php

namespace Tests\Unit\Services\Pricing;

use App\Models\Commodity;
use App\Models\EconomicShock;
use App\Models\Galaxy;
use App\Models\HubCommodityStats;
use App\Models\TradingHub;
use App\Services\Pricing\PricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PricingServiceTest extends TestCase
{
    use RefreshDatabase;

    private PricingService $pricingService;
    private Galaxy $galaxy;
    private TradingHub $hub;
    private Commodity $commodity;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pricingService = app(PricingService::class);

        $this->galaxy = Galaxy::factory()->create();
        $this->hub = TradingHub::factory()->create();
        $this->commodity = Commodity::factory()->create([
            'base_price' => 1000,
            'price_min_multiplier' => 0.1,
            'price_max_multiplier' => 10.0,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function  it_computes_coverage_based_price(): void
    {
        $stats = HubCommodityStats::factory()->create([
            'trading_hub_id' => $this->hub->id,
            'commodity_id' => $this->commodity->id,
            'avg_daily_demand' => 100,
            'avg_daily_supply' => 100,
        ]);

        $result = $this->pricingService->computePriceCoverageBased(
            $this->hub,
            $this->commodity,
            $stats,
            onHandQty: 700 // 7 days of coverage
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('buy_price', $result);
        $this->assertArrayHasKey('sell_price', $result);
        $this->assertArrayHasKey('mid_price', $result);
        $this->assertArrayHasKey('components', $result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function  it_applies_spread_to_price(): void
    {
        $stats = HubCommodityStats::factory()->create([
            'trading_hub_id' => $this->hub->id,
            'commodity_id' => $this->commodity->id,
            'avg_daily_demand' => 100,
        ]);

        $result = $this->pricingService->computePriceCoverageBased(
            $this->hub,
            $this->commodity,
            $stats,
            onHandQty: 700
        );

        $spread = $result['components']['spread_buy'];
        $midPrice = $result['mid_price'];

        // buy_price should be higher than mid_price
        $this->assertGreaterThan($midPrice, $result['buy_price']);
        // sell_price should be lower than mid_price
        $this->assertLessThan($midPrice, $result['sell_price']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function  scarcity_multiplier_increases_at_low_coverage(): void
    {
        $stats = HubCommodityStats::factory()->create([
            'trading_hub_id' => $this->hub->id,
            'commodity_id' => $this->commodity->id,
            'avg_daily_demand' => 100,
        ]);

        // 0.5 days coverage (very scarce)
        $scarceResult = $this->pricingService->computePriceCoverageBased(
            $this->hub,
            $this->commodity,
            $stats,
            onHandQty: 50
        );

        // 7 days coverage (neutral)
        $neutralResult = $this->pricingService->computePriceCoverageBased(
            $this->hub,
            $this->commodity,
            $stats,
            onHandQty: 700
        );

        // Scarce should have higher mid_price than neutral
        $this->assertGreaterThan(
            $neutralResult['mid_price'],
            $scarceResult['mid_price'],
            'Scarce coverage should yield higher price'
        );
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function  scarcity_multiplier_decreases_at_high_coverage(): void
    {
        $stats = HubCommodityStats::factory()->create([
            'trading_hub_id' => $this->hub->id,
            'commodity_id' => $this->commodity->id,
            'avg_daily_demand' => 100,
        ]);

        // 7 days coverage (neutral)
        $neutralResult = $this->pricingService->computePriceCoverageBased(
            $this->hub,
            $this->commodity,
            $stats,
            onHandQty: 700
        );

        // 30 days coverage (plenty)
        $plentyResult = $this->pricingService->computePriceCoverageBased(
            $this->hub,
            $this->commodity,
            $stats,
            onHandQty: 3000
        );

        // Plenty should have lower mid_price than neutral
        $this->assertLessThan(
            $neutralResult['mid_price'],
            $plentyResult['mid_price'],
            'High coverage should yield lower price'
        );
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function  it_clamps_price_within_min_max_multiplier(): void
    {
        $stats = HubCommodityStats::factory()->create([
            'trading_hub_id' => $this->hub->id,
            'commodity_id' => $this->commodity->id,
            'avg_daily_demand' => 100,
        ]);

        // Extremely high coverage (way above 30 days)
        $result = $this->pricingService->computePriceCoverageBased(
            $this->hub,
            $this->commodity,
            $stats,
            onHandQty: 100000
        );

        $minPrice = $this->commodity->base_price * $this->commodity->price_min_multiplier;
        $maxPrice = $this->commodity->base_price * $this->commodity->price_max_multiplier;

        $this->assertGreaterThanOrEqual($minPrice, $result['mid_price']);
        $this->assertLessThanOrEqual($maxPrice, $result['mid_price']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function  it_includes_component_breakdown_in_result(): void
    {
        $stats = HubCommodityStats::factory()->create([
            'trading_hub_id' => $this->hub->id,
            'commodity_id' => $this->commodity->id,
            'avg_daily_demand' => 100,
        ]);

        $result = $this->pricingService->computePriceCoverageBased(
            $this->hub,
            $this->commodity,
            $stats,
            onHandQty: 700
        );

        $components = $result['components'];

        $this->assertArrayHasKey('base_price', $components);
        $this->assertArrayHasKey('scarcity_mult', $components);
        $this->assertArrayHasKey('shock_mult', $components);
        $this->assertArrayHasKey('coverage_days', $components);
        $this->assertArrayHasKey('spread_buy', $components);
        $this->assertArrayHasKey('spread_sell', $components);

        $this->assertEquals($this->commodity->base_price, $components['base_price']);
        $this->assertEquals(7, $components['coverage_days']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function  it_applies_economic_shock_multiplier(): void
    {
        $stats = HubCommodityStats::factory()->create([
            'trading_hub_id' => $this->hub->id,
            'commodity_id' => $this->commodity->id,
            'avg_daily_demand' => 100,
        ]);

        // Create a positive shock (BOOM - price increase)
        EconomicShock::factory()->create([
            'galaxy_id' => $this->galaxy->id,
            'commodity_id' => $this->commodity->id,
            'is_active' => true,
            'magnitude' => 0.25, // +25%
            'decay_half_life_ticks' => 1000,
        ]);

        $result = $this->pricingService->computePriceCoverageBased(
            $this->hub,
            $this->commodity,
            $stats,
            onHandQty: 700
        );

        // Shock multiplier should be > 1.0
        $this->assertGreaterThan(1.0, $result['components']['shock_mult']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function  it_handles_missing_stats_gracefully(): void
    {
        // Don't create stats - should use defaults
        $result = $this->pricingService->computePriceCoverageBased(
            $this->hub,
            $this->commodity,
            stats: null,
            onHandQty: 700
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('buy_price', $result);
        $this->assertArrayHasKey('sell_price', $result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function  it_handles_zero_demand_without_division_by_zero(): void
    {
        $stats = HubCommodityStats::factory()->create([
            'trading_hub_id' => $this->hub->id,
            'commodity_id' => $this->commodity->id,
            'avg_daily_demand' => 0, // Zero demand
        ]);

        // Should not throw division by zero
        $result = $this->pricingService->computePriceCoverageBased(
            $this->hub,
            $this->commodity,
            $stats,
            onHandQty: 700
        );

        $this->assertIsArray($result);
        $this->assertFinite($result['buy_price']);
        $this->assertFinite($result['sell_price']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function buy_and_sell_prices_are_integers(): void
    {
        $stats = HubCommodityStats::factory()->create([
            'trading_hub_id' => $this->hub->id,
            'commodity_id' => $this->commodity->id,
            'avg_daily_demand' => 100,
        ]);

        $result = $this->pricingService->computePriceCoverageBased(
            $this->hub,
            $this->commodity,
            $stats,
            onHandQty: 700
        );

        $this->assertIsInt($result['buy_price']);
        $this->assertIsInt($result['sell_price']);
        $this->assertIsInt($result['mid_price']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function coverage_days_is_computed_correctly(): void
    {
        $stats = HubCommodityStats::factory()->create([
            'trading_hub_id' => $this->hub->id,
            'commodity_id' => $this->commodity->id,
            'avg_daily_demand' => 100,
        ]);

        $result = $this->pricingService->computePriceCoverageBased(
            $this->hub,
            $this->commodity,
            $stats,
            onHandQty: 1500
        );

        // 1500 on_hand / 100 daily_demand = 15 days
        $this->assertEquals(15, $result['components']['coverage_days']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function multiple_shocks_compound_correctly(): void
    {
        $stats = HubCommodityStats::factory()->create([
            'trading_hub_id' => $this->hub->id,
            'commodity_id' => $this->commodity->id,
            'avg_daily_demand' => 100,
        ]);

        // Create two positive shocks
        EconomicShock::factory()->create([
            'galaxy_id' => $this->galaxy->id,
            'commodity_id' => $this->commodity->id,
            'is_active' => true,
            'magnitude' => 0.25, // +25%
            'decay_half_life_ticks' => 1000,
        ]);

        EconomicShock::factory()->create([
            'galaxy_id' => $this->galaxy->id,
            'commodity_id' => $this->commodity->id,
            'is_active' => true,
            'magnitude' => 0.50, // +50%
            'decay_half_life_ticks' => 1000,
        ]);

        $result = $this->pricingService->computePriceCoverageBased(
            $this->hub,
            $this->commodity,
            $stats,
            onHandQty: 700
        );

        // Shocks should compound: (1 + 0.25) * (1 + 0.50) = 1.875
        $expectedShockMult = 1.25 * 1.50;
        $this->assertEqualsWithDelta($expectedShockMult, $result['components']['shock_mult'], 0.01);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function system_wide_shocks_apply_to_all_commodities(): void
    {
        $stats = HubCommodityStats::factory()->create([
            'trading_hub_id' => $this->hub->id,
            'commodity_id' => $this->commodity->id,
            'avg_daily_demand' => 100,
        ]);

        // Create system-wide shock (commodity_id = null)
        EconomicShock::factory()->create([
            'galaxy_id' => $this->galaxy->id,
            'commodity_id' => null, // System-wide
            'is_active' => true,
            'magnitude' => 0.40,
            'decay_half_life_ticks' => 1000,
        ]);

        $result = $this->pricingService->computePriceCoverageBased(
            $this->hub,
            $this->commodity,
            $stats,
            onHandQty: 700
        );

        // Should be affected by system-wide shock
        $this->assertGreaterThan(1.0, $result['components']['shock_mult']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function  it_respects_hub_configured_spreads(): void
    {
        $hubWithCustomSpreads = TradingHub::factory()->create([
            'spread_buy' => 0.15,
            'spread_sell' => 0.10,
        ]);

        $stats = HubCommodityStats::factory()->create([
            'trading_hub_id' => $hubWithCustomSpreads->id,
            'commodity_id' => $this->commodity->id,
            'avg_daily_demand' => 100,
        ]);

        $result = $this->pricingService->computePriceCoverageBased(
            $hubWithCustomSpreads,
            $this->commodity,
            $stats,
            onHandQty: 700
        );

        $this->assertEquals(0.15, $result['components']['spread_buy']);
        $this->assertEquals(0.10, $result['components']['spread_sell']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function price_is_deterministic_given_same_inputs(): void
    {
        $stats = HubCommodityStats::factory()->create([
            'trading_hub_id' => $this->hub->id,
            'commodity_id' => $this->commodity->id,
            'avg_daily_demand' => 100,
        ]);

        $result1 = $this->pricingService->computePriceCoverageBased(
            $this->hub,
            $this->commodity,
            $stats,
            onHandQty: 700
        );

        $result2 = $this->pricingService->computePriceCoverageBased(
            $this->hub,
            $this->commodity,
            $stats,
            onHandQty: 700
        );

        $this->assertEquals($result1['buy_price'], $result2['buy_price']);
        $this->assertEquals($result1['sell_price'], $result2['sell_price']);
        $this->assertEquals($result1['mid_price'], $result2['mid_price']);
    }
}
