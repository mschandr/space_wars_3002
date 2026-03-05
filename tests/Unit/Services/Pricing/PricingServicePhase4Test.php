<?php

namespace Tests\Unit\Services\Pricing;

use App\Models\Commodity;
use App\Models\Galaxy;
use App\Models\HubCommodityStats;
use App\Models\TradingHub;
use App\Services\Pricing\PricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PricingServicePhase4Test extends TestCase
{
    use RefreshDatabase;

    private PricingService $service;

    private Galaxy $galaxy;

    private TradingHub $hub;

    private Commodity $commodity;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PricingService::class);

        $this->galaxy = Galaxy::factory()->create();
        $this->hub = TradingHub::factory()->create(['galaxy_id' => $this->galaxy->id]);
        $this->commodity = Commodity::factory()->create(['base_price' => 1000]);
    }

    public function test_compute_price_rejects_negative_on_hand_qty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('onHandQty cannot be negative');

        $this->service->computePriceCoverageBased(
            $this->hub,
            $this->commodity,
            null,
            -100,  // negative on-hand quantity
            null
        );
    }

    public function test_compute_price_rejects_negative_requested_qty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('requestedQty cannot be negative');

        $this->service->computePriceCoverageBased(
            $this->hub,
            $this->commodity,
            null,
            1000,  // valid on-hand
            -500   // negative requested quantity
        );
    }

    public function test_compute_price_accepts_zero_on_hand_qty(): void
    {
        $result = $this->service->computePriceCoverageBased(
            $this->hub,
            $this->commodity,
            null,
            0,  // zero is valid
            null
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('buy_price', $result);
        $this->assertArrayHasKey('sell_price', $result);
    }

    public function test_compute_price_accepts_zero_requested_qty(): void
    {
        $result = $this->service->computePriceCoverageBased(
            $this->hub,
            $this->commodity,
            null,
            1000,
            0  // zero is valid
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('buy_price', $result);
    }

    public function test_compute_price_with_positive_quantities(): void
    {
        $result = $this->service->computePriceCoverageBased(
            $this->hub,
            $this->commodity,
            null,
            5000,  // positive on-hand
            1000   // positive requested
        );

        $this->assertIsArray($result);
        $this->assertTrue($result['buy_price'] >= 0);
        $this->assertTrue($result['sell_price'] >= 0);
    }

    public function test_demand_estimation_fallback_base_price_ratio(): void
    {
        config(['economy.demand_estimation.fallback_method' => 'base_price_ratio']);

        // Call computePriceCoverageBased with zero demand to trigger fallback
        $result = $this->service->computePriceCoverageBased(
            $this->hub,
            $this->commodity,
            HubCommodityStats::factory()->create([
                'trading_hub_id' => $this->hub->id,
                'commodity_id' => $this->commodity->id,
                'avg_daily_demand' => 0,  // zero demand triggers fallback
            ]),
            1000,
            null
        );

        // Should return valid prices without division by zero
        $this->assertIsArray($result);
        $this->assertGreaterThan(0, $result['buy_price']);
        $this->assertGreaterThan(0, $result['sell_price']);
    }

    public function test_demand_estimation_fallback_static(): void
    {
        config(['economy.demand_estimation.fallback_method' => 'static']);
        config(['economy.demand_estimation.fallback_daily_demand' => 50]);

        $result = $this->service->computePriceCoverageBased(
            $this->hub,
            $this->commodity,
            HubCommodityStats::factory()->create([
                'trading_hub_id' => $this->hub->id,
                'commodity_id' => $this->commodity->id,
                'avg_daily_demand' => 0,
            ]),
            1000,
            null
        );

        $this->assertIsArray($result);
        $this->assertGreaterThan(0, $result['buy_price']);
    }

    public function test_volume_fee_integration_in_pricing(): void
    {
        config(['economy.anti_cornering.enabled' => true]);
        config(['economy.anti_cornering.volume_fee' => [
            'threshold' => 500,
            'fee_per_unit' => 0.001,
            'max_additional_spread' => 0.25,
        ]]);

        // Request quantity above threshold
        $result = $this->service->computePriceCoverageBased(
            $this->hub,
            $this->commodity,
            HubCommodityStats::factory()->create([
                'trading_hub_id' => $this->hub->id,
                'commodity_id' => $this->commodity->id,
                'avg_daily_demand' => 100,
            ]),
            5000,
            1000  // Above threshold triggers fee
        );

        // Volume fee should increase the buy spread
        $this->assertGreaterThan(0, $result['components']['volume_fee']);
        $this->assertArrayHasKey('volume_fee', $result['components']);
    }

    public function test_no_volume_fee_when_anti_cornering_disabled(): void
    {
        config(['economy.anti_cornering.enabled' => false]);

        $result = $this->service->computePriceCoverageBased(
            $this->hub,
            $this->commodity,
            HubCommodityStats::factory()->create([
                'trading_hub_id' => $this->hub->id,
                'commodity_id' => $this->commodity->id,
                'avg_daily_demand' => 100,
            ]),
            5000,
            5000  // Large request, but fee disabled
        );

        $this->assertEquals(0.0, $result['components']['volume_fee']);
    }
}
