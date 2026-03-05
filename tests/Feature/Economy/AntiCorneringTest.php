<?php

namespace Tests\Feature\Economy;

use App\Models\Commodity;
use App\Models\Galaxy;
use App\Models\Player;
use App\Models\PointOfInterest;
use App\Models\TradingHub;
use App\Services\Economy\AntiCorneringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AntiCorneringTest extends TestCase
{
    use RefreshDatabase;

    private AntiCorneringService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AntiCorneringService::class);
        config(['economy.anti_cornering.enabled' => true]);
        config(['economy.anti_cornering.volume_fee' => [
            'threshold' => 500,
            'fee_per_unit' => 0.001,
            'max_additional_spread' => 0.25,
        ]]);
    }

    public function test_no_fee_below_threshold(): void
    {
        $adjustment = $this->service->computeVolumeAdjustment(400, null);

        $this->assertEquals(0.0, $adjustment);
    }

    public function test_volume_fee_applied_above_threshold(): void
    {
        $adjustment = $this->service->computeVolumeAdjustment(1000, null);

        // 500 units above threshold * 0.001 = 0.5, capped at 0.25
        $this->assertEquals(0.25, $adjustment);
    }

    public function test_fee_capped_at_max_spread(): void
    {
        $adjustment = $this->service->computeVolumeAdjustment(2000, null);

        // Would be 1500 * 0.001 = 1.5, but capped at 0.25
        $this->assertLessThanOrEqual(0.25, $adjustment);
    }

    public function test_purchase_limit_enforced_per_day(): void
    {
        config(['economy.anti_cornering.max_purchase_per_tick' => 1000]);

        $player = Player::factory()->create();

        $canPurchase = $this->service->canPurchaseThisTick($player, 900);
        $this->assertTrue($canPurchase);

        $canPurchase = $this->service->canPurchaseThisTick($player, 100);
        $this->assertTrue($canPurchase);

        $canPurchase = $this->service->canPurchaseThisTick($player, 1);
        $this->assertFalse($canPurchase);
    }

    public function test_multiple_small_trades_bypass_fee(): void
    {
        // 3 small trades of 200 each = 600 total, but each individually is below 500 threshold
        $adjustments = [
            $this->service->computeVolumeAdjustment(200, null),
            $this->service->computeVolumeAdjustment(200, null),
            $this->service->computeVolumeAdjustment(200, null),
        ];

        foreach ($adjustments as $adj) {
            $this->assertEquals(0.0, $adj);
        }
    }

    public function test_large_single_trade_triggers_fee(): void
    {
        $adjustment = $this->service->computeVolumeAdjustment(1500, null);

        // (1500 - 500) * 0.001 = 1.0, capped at 0.25
        $this->assertEquals(0.25, $adjustment);
    }

    public function test_fee_breaks_cornering_attempts(): void
    {
        // Cornering attempt: buy a large quantity
        $normalSpread = 0.08;
        $volumeFee = $this->service->computeVolumeAdjustment(5000, null);

        $totalSpread = $normalSpread + $volumeFee;

        // With volume fee, total spread is higher, making the purchase less profitable
        $this->assertGreaterThan($normalSpread, $totalSpread);
    }

    public function test_anti_cornering_disabled_returns_zero(): void
    {
        config(['economy.anti_cornering.enabled' => false]);

        $adjustment = $this->service->computeVolumeAdjustment(10000, null);

        $this->assertEquals(0.0, $adjustment);
    }

    public function test_purchase_block_reason_message(): void
    {
        config(['economy.anti_cornering.max_purchase_per_tick' => 1000]);

        $player = Player::factory()->create();

        // This should exceed limit
        $reason = $this->service->getPurchaseBlockReason($player, 1001);

        $this->assertNotNull($reason);
        $this->assertStringContainsString('limit exceeded', $reason);
    }
}
