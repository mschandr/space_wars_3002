<?php

namespace Tests\Unit\Services\Economy;

use App\Models\Commodity;
use App\Models\Player;
use App\Services\Economy\AntiCorneringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AntiCorneringServiceTest extends TestCase
{
    use RefreshDatabase;

    private AntiCorneringService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AntiCorneringService::class);
    }

    public function test_volume_adjustment_zero_below_threshold(): void
    {
        config(['economy.anti_cornering.enabled' => true]);
        config(['economy.anti_cornering.volume_fee' => [
            'threshold' => 500,
            'fee_per_unit' => 0.001,
            'max_additional_spread' => 0.25,
        ]]);

        $adjustment = $this->service->computeVolumeAdjustment(400, null);

        $this->assertEquals(0.0, $adjustment);
    }

    public function test_volume_adjustment_fee_above_threshold(): void
    {
        config(['economy.anti_cornering.enabled' => true]);
        config(['economy.anti_cornering.volume_fee' => [
            'threshold' => 500,
            'fee_per_unit' => 0.001,
            'max_additional_spread' => 0.25,
        ]]);

        $adjustment = $this->service->computeVolumeAdjustment(700, null);

        // 200 units above threshold * 0.001 = 0.2
        $this->assertEquals(0.2, $adjustment);
    }

    public function test_volume_adjustment_capped_at_max(): void
    {
        config(['economy.anti_cornering.enabled' => true]);
        config(['economy.anti_cornering.volume_fee' => [
            'threshold' => 500,
            'fee_per_unit' => 0.001,
            'max_additional_spread' => 0.25,
        ]]);

        // 600 units above threshold * 0.001 = 0.6, but capped at 0.25
        $adjustment = $this->service->computeVolumeAdjustment(1100, null);

        $this->assertEquals(0.25, $adjustment);
    }

    public function test_disabled_anti_cornering_returns_zero(): void
    {
        config(['economy.anti_cornering.enabled' => false]);

        $adjustment = $this->service->computeVolumeAdjustment(1000, null);

        $this->assertEquals(0.0, $adjustment);
    }

    public function test_can_purchase_below_limit(): void
    {
        config(['economy.anti_cornering.enabled' => true]);
        config(['economy.anti_cornering.max_purchase_per_tick' => 1000]);

        $player = Player::factory()->create();

        $result = $this->service->canPurchaseThisTick($player, 500);

        $this->assertTrue($result);
    }

    public function test_can_purchase_at_limit(): void
    {
        config(['economy.anti_cornering.enabled' => true]);
        config(['economy.anti_cornering.max_purchase_per_tick' => 1000]);

        $player = Player::factory()->create();

        $result = $this->service->canPurchaseThisTick($player, 1000);

        $this->assertTrue($result);
    }

    public function test_can_purchase_disabled_limit(): void
    {
        config(['economy.anti_cornering.enabled' => true]);
        config(['economy.anti_cornering.max_purchase_per_tick' => null]);

        $player = Player::factory()->create();

        $result = $this->service->canPurchaseThisTick($player, 999999);

        $this->assertTrue($result);
    }

    public function test_disabled_service_allows_all_purchases(): void
    {
        config(['economy.anti_cornering.enabled' => false]);
        config(['economy.anti_cornering.max_purchase_per_tick' => 100]);

        $player = Player::factory()->create();

        $result = $this->service->canPurchaseThisTick($player, 999999);

        $this->assertTrue($result);
    }

    public function test_get_purchase_block_reason_when_exceeded(): void
    {
        config(['economy.anti_cornering.enabled' => true]);
        config(['economy.anti_cornering.max_purchase_per_tick' => 1000]);

        $player = Player::factory()->create();

        $reason = $this->service->getPurchaseBlockReason($player, 1001);

        $this->assertNotNull($reason);
        $this->assertStringContainsString('limit exceeded', $reason);
    }

    public function test_get_purchase_block_reason_when_allowed(): void
    {
        config(['economy.anti_cornering.enabled' => true]);
        config(['economy.anti_cornering.max_purchase_per_tick' => 1000]);

        $player = Player::factory()->create();

        $reason = $this->service->getPurchaseBlockReason($player, 500);

        $this->assertNull($reason);
    }

    public function test_negative_quantity_throws_exception_on_volume_adjustment(): void
    {
        config(['economy.anti_cornering.enabled' => true]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot be negative');

        $this->service->computeVolumeAdjustment(-100, null);
    }

    public function test_negative_quantity_throws_exception_on_can_purchase(): void
    {
        config(['economy.anti_cornering.enabled' => true]);

        $player = Player::factory()->create();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot be negative');

        $this->service->canPurchaseThisTick($player, -100);
    }

    public function test_negative_quantity_throws_exception_on_block_reason(): void
    {
        config(['economy.anti_cornering.enabled' => true]);

        $player = Player::factory()->create();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot be negative');

        $this->service->getPurchaseBlockReason($player, -100);
    }
}
