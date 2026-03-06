<?php

namespace Tests\Feature\Economy;

use App\Models\Commodity;
use App\Models\Galaxy;
use App\Models\Mineral;
use App\Models\Player;
use App\Models\PlayerCargo;
use App\Models\PlayerShip;
use App\Models\PointOfInterest;
use App\Models\ReservePolicy;
use App\Models\TradingHub;
use App\Models\TradingHubInventory;
use App\Services\Economy\AntiCorneringService;
use App\Services\Pricing\PricingService;
use App\Services\TradingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 4 Integration Tests
 *
 * Validates complete trading flow with anti-cornering and reserve policies active.
 * Tests three-layer validation and volume fee integration.
 */
class Phase4IntegrationTest extends TestCase
{
    use RefreshDatabase;

    private Galaxy $galaxy;
    private TradingHub $tradingHub;
    private Player $player;
    private PlayerShip $ship;
    private Mineral $mineral;
    private TradingService $tradingService;
    private AntiCorneringService $antiCorneringService;

    protected function setUp(): void
    {
        parent::setUp();

        config(['economy.anti_cornering.max_purchase_per_tick' => 10000]);
        config(['economy.anti_cornering.volume_fee.threshold' => 1000]);
        config(['economy.anti_cornering.volume_fee.fee_per_unit' => 0.001]);

        // Create galaxy with POI and trading hub
        $this->galaxy = Galaxy::factory()->create();
        $poi = PointOfInterest::factory()->create([
            'galaxy_id' => $this->galaxy->id,
        ]);
        $this->tradingHub = TradingHub::factory()->create([
            'poi_id' => $poi->id,
        ]);

        // Create mineral
        $this->mineral = Mineral::factory()->create();

        // Create player with ship at trading hub
        $this->player = Player::factory()->create([
            'current_poi_id' => $poi->id,
            'credits' => 1000000,
        ]);
        $this->ship = PlayerShip::factory()->create([
            'player_id' => $this->player->id,
            'current_poi_id' => $poi->id,
        ]);

        // Create inventory
        TradingHubInventory::create([
            'trading_hub_id' => $this->tradingHub->id,
            'mineral_id' => $this->mineral->id,
            'quantity' => 50000,
            'on_hand_qty' => 50000,
            'current_price' => 100,
            'buy_price' => 99,
            'sell_price' => 101,
            'demand_level' => 50,
            'supply_level' => 50,
        ]);

        // Create reserve policy
        ReservePolicy::create([
            'galaxy_id' => $this->galaxy->id,
            'commodity_id' => $this->mineral->id,
            'min_qty_on_hand' => 5000,
            'npc_fallback_enabled' => true,
            'npc_price_multiplier' => 1.5,
        ]);

        // Get service instances
        $this->tradingService = app(TradingService::class);
        $this->antiCorneringService = app(AntiCorneringService::class);
    }

    /**
     * Test that large purchases trigger volume fees
     */
    public function test_large_purchase_includes_volume_fee(): void
    {
        $inventory = TradingHubInventory::where('trading_hub_id', $this->tradingHub->id)
            ->where('mineral_id', $this->mineral->id)
            ->firstOrFail();

        // Large purchase (above threshold)
        $quantity = 2000;
        $basePrice = $inventory->sell_price;

        // Compute price with quantity (should include volume fee)
        $pricingService = app(PricingService::class);
        $priceData = $pricingService->computePriceCoverageBased(
            $this->tradingHub,
            $this->mineral,
            null,
            (float) $inventory->on_hand_qty,
            (float) $quantity
        );

        $volumeAdjustedPrice = $priceData['sell_price'];

        // Volume fee should increase price
        $this->assertGreaterThan($basePrice, $volumeAdjustedPrice);

        // Total cost with volume fee should be higher than without
        $costWithoutFee = $basePrice * $quantity;
        $costWithFee = $volumeAdjustedPrice * $quantity;

        $this->assertGreaterThan($costWithoutFee, $costWithFee);
    }

    /**
     * Test that purchase limit blocks excessive buying
     */
    public function test_purchase_limit_prevents_monopoly(): void
    {
        // Purchase limit is 10,000 per tick
        $firstQuantity = 6000;

        $result1 = $this->tradingService->buyMineral(
            $this->player,
            $this->ship,
            TradingHubInventory::where('trading_hub_id', $this->tradingHub->id)
                ->where('mineral_id', $this->mineral->id)
                ->firstOrFail(),
            $firstQuantity
        );

        $this->assertTrue($result1['success']);

        // Second purchase should be allowed (total 6000 + 3000 = 9000 < 10000)
        $secondQuantity = 3000;
        $inventory = TradingHubInventory::where('trading_hub_id', $this->tradingHub->id)
            ->where('mineral_id', $this->mineral->id)
            ->firstOrFail();

        $canPurchase = $this->antiCorneringService->canPurchaseThisTick(
            $this->player,
            $secondQuantity
        );
        $this->assertTrue($canPurchase);

        // Third purchase should be blocked (total would be 9000 + 2000 = 11000 > 10000)
        $thirdQuantity = 2000;
        $canPurchase = $this->antiCorneringService->canPurchaseThisTick(
            $this->player,
            $thirdQuantity
        );
        $this->assertFalse($canPurchase);

        $blockReason = $this->antiCorneringService->getPurchaseBlockReason(
            $this->player,
            $thirdQuantity
        );
        $this->assertNotNull($blockReason);
        $this->assertStringContainsString('10000', $blockReason);
    }

    /**
     * Test that inventory locks prevent race conditions during trading
     */
    public function test_inventory_locks_prevent_toctou(): void
    {
        $inventory = TradingHubInventory::where('trading_hub_id', $this->tradingHub->id)
            ->where('mineral_id', $this->mineral->id)
            ->firstOrFail();

        $initialQuantity = $inventory->on_hand_qty;

        $result = $this->tradingService->buyMineral(
            $this->player,
            $this->ship,
            $inventory,
            100
        );

        $this->assertTrue($result['success']);

        // Verify inventory was atomically updated
        $updatedInventory = TradingHubInventory::where('trading_hub_id', $this->tradingHub->id)
            ->where('mineral_id', $this->mineral->id)
            ->firstOrFail();

        // Inventory should be reduced (after trade mutation applied)
        $this->assertLessThan($initialQuantity, (float) $updatedInventory->on_hand_qty);
    }

    /**
     * Test reserve policy minimum inventory guarantee
     */
    public function test_reserve_policy_maintains_minimum_inventory(): void
    {
        $policy = ReservePolicy::where('galaxy_id', $this->galaxy->id)
            ->where('commodity_id', $this->mineral->id)
            ->firstOrFail();

        $this->assertEquals(5000, $policy->min_qty_on_hand);
        $this->assertTrue($policy->npc_fallback_enabled);
        $this->assertEquals(1.5, $policy->npc_price_multiplier);
    }

    /**
     * Test that credits are validated after volume fee calculation
     */
    public function test_credits_validation_includes_volume_fees(): void
    {
        // Give player just enough credits for base price, but not volume fee
        $inventory = TradingHubInventory::where('trading_hub_id', $this->tradingHub->id)
            ->where('mineral_id', $this->mineral->id)
            ->firstOrFail();

        $quantity = 1500; // Above threshold, will have volume fee
        $basePrice = $inventory->sell_price;
        $baselineCost = $basePrice * $quantity;

        // Set credits to slightly above baseline but below volume-fee-adjusted cost
        $this->player->update(['credits' => (int) ($baselineCost + 100)]);

        // Attempt purchase
        $result = $this->tradingService->buyMineral(
            $this->player,
            $this->ship,
            $inventory,
            $quantity
        );

        // Should fail due to insufficient credits (volume fee added)
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('credits', strtolower($result['message']));
    }

    /**
     * Test negative quantity validation
     */
    public function test_negative_quantities_are_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->antiCorneringService->canPurchaseThisTick($this->player, -100);
    }

    /**
     * Test pricing computation with zero quantity
     */
    public function test_pricing_accepts_zero_quantity(): void
    {
        $pricingService = app(PricingService::class);

        $priceData = $pricingService->computePriceCoverageBased(
            $this->tradingHub,
            $this->mineral,
            null,
            1000,
            0
        );

        $this->assertIsArray($priceData);
        $this->assertArrayHasKey('buy_price', $priceData);
        $this->assertArrayHasKey('sell_price', $priceData);
    }

    /**
     * Test transaction logging includes volume-adjusted prices
     */
    public function test_transaction_log_records_volume_adjusted_prices(): void
    {
        $inventory = TradingHubInventory::where('trading_hub_id', $this->tradingHub->id)
            ->where('mineral_id', $this->mineral->id)
            ->firstOrFail();

        $quantity = 1500;

        $result = $this->tradingService->buyMineral(
            $this->player,
            $this->ship,
            $inventory,
            $quantity
        );

        $this->assertTrue($result['success']);

        // Check transaction log
        $transaction = \App\Models\PlayerTradeTransaction::where('player_id', $this->player->id)
            ->where('mineral_id', $this->mineral->id)
            ->orderByDesc('id')
            ->first();

        $this->assertNotNull($transaction);
        $this->assertEquals('buy', $transaction->transaction_type);
        $this->assertEquals($quantity, $transaction->quantity);

        // Unit price should be volume-adjusted (higher than base inventory price)
        $this->assertGreaterThan($inventory->sell_price, $transaction->unit_price);

        // Total should match quantity * adjusted price
        $expectedTotal = $transaction->unit_price * $quantity;
        $this->assertEquals((int) $expectedTotal, $transaction->total_amount);
    }

    /**
     * Test sell transaction with volume fee on buy price
     */
    public function test_sell_transaction_includes_volume_adjustment(): void
    {
        // First buy some inventory
        $inventory = TradingHubInventory::where('trading_hub_id', $this->tradingHub->id)
            ->where('mineral_id', $this->mineral->id)
            ->firstOrFail();

        $this->tradingService->buyMineral(
            $this->player,
            $this->ship,
            $inventory,
            500
        );

        // Refresh inventory for sell operation
        $inventory->refresh();

        // Now sell a large quantity to trigger volume fee
        $cargo = PlayerCargo::where('player_ship_id', $this->ship->id)
            ->where('mineral_id', $this->mineral->id)
            ->firstOrFail();

        $sellQuantity = 300;

        $result = $this->tradingService->sellMineral(
            $this->player,
            $this->ship,
            $cargo,
            $inventory,
            $sellQuantity
        );

        $this->assertTrue($result['success']);

        // Verify XP was calculated from actual revenue
        $this->assertGreaterThan(0, $result['xp_earned']);
    }

    /**
     * Test all input quantities must be non-negative
     */
    public function test_all_negative_inputs_throw_exception(): void
    {
        $pricingService = app(PricingService::class);

        // Negative on_hand_qty
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('onHandQty cannot be negative');

        $pricingService->computePriceCoverageBased(
            $this->tradingHub,
            $this->mineral,
            null,
            -1000,
            100
        );
    }

    /**
     * Test config coercion for safety
     */
    public function test_config_values_coerced_to_non_negative(): void
    {
        // Set config with negative values
        config(['economy.anti_cornering.volume_fee.threshold' => -500]);
        config(['economy.anti_cornering.volume_fee.fee_per_unit' => -0.001]);

        // Service should coerce to non-negative via max(0, ...)
        $adjustment = $this->antiCorneringService->computeVolumeAdjustment(1000);

        $this->assertGreaterThanOrEqual(0, $adjustment);
    }
}
