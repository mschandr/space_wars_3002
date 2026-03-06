<?php

namespace Tests\Feature\Economy;

use App\Models\Commodity;
use App\Models\Galaxy;
use App\Models\ReservePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservePolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['economy.reserves.enabled' => true]);
    }

    public function test_reserve_policy_creation(): void
    {
        $galaxy = Galaxy::factory()->create();
        $commodity = Commodity::factory()->create();

        $policy = ReservePolicy::create([
            'galaxy_id' => $galaxy->id,
            'commodity_id' => $commodity->id,
            'min_qty_on_hand' => 5000,
            'npc_fallback_enabled' => true,
            'npc_price_multiplier' => 1.5,
        ]);

        $this->assertDatabaseHas('reserve_policies', [
            'galaxy_id' => $galaxy->id,
            'commodity_id' => $commodity->id,
            'min_qty_on_hand' => 5000,
        ]);
    }

    public function test_reserve_policy_relationships(): void
    {
        $galaxy = Galaxy::factory()->create();
        $commodity = Commodity::factory()->create();

        $policy = ReservePolicy::create([
            'galaxy_id' => $galaxy->id,
            'commodity_id' => $commodity->id,
            'min_qty_on_hand' => 5000,
            'npc_fallback_enabled' => true,
            'npc_price_multiplier' => 1.5,
        ]);

        $this->assertTrue($policy->galaxy->is($galaxy));
        $this->assertTrue($policy->commodity->is($commodity));
    }

    public function test_reserve_policy_unique_per_galaxy_commodity(): void
    {
        $galaxy = Galaxy::factory()->create();
        $commodity = Commodity::factory()->create();

        ReservePolicy::create([
            'galaxy_id' => $galaxy->id,
            'commodity_id' => $commodity->id,
            'min_qty_on_hand' => 5000,
            'npc_fallback_enabled' => true,
            'npc_price_multiplier' => 1.5,
        ]);

        // Try to create duplicate - should fail
        $this->expectException(\Illuminate\Database\QueryException::class);

        ReservePolicy::create([
            'galaxy_id' => $galaxy->id,
            'commodity_id' => $commodity->id,
            'min_qty_on_hand' => 6000,
            'npc_fallback_enabled' => true,
            'npc_price_multiplier' => 1.5,
        ]);
    }

    public function test_reserve_policy_system_wide_commodity_null(): void
    {
        $galaxy = Galaxy::factory()->create();

        // System-wide policy (commodity_id = null)
        $policy = ReservePolicy::create([
            'galaxy_id' => $galaxy->id,
            'commodity_id' => null,
            'min_qty_on_hand' => 10000,
            'npc_fallback_enabled' => true,
            'npc_price_multiplier' => 1.5,
        ]);

        $this->assertNull($policy->commodity_id);
        $this->assertEquals($galaxy->id, $policy->galaxy_id);
    }

    public function test_reserve_policy_npc_fallback_disabled(): void
    {
        $galaxy = Galaxy::factory()->create();
        $commodity = Commodity::factory()->create();

        $policy = ReservePolicy::create([
            'galaxy_id' => $galaxy->id,
            'commodity_id' => $commodity->id,
            'min_qty_on_hand' => 5000,
            'npc_fallback_enabled' => false,
            'npc_price_multiplier' => 1.5,
        ]);

        $this->assertFalse($policy->npc_fallback_enabled);
    }

    public function test_reserve_policy_price_multiplier(): void
    {
        $galaxy = Galaxy::factory()->create();
        $commodity = Commodity::factory()->create();

        $policy = ReservePolicy::create([
            'galaxy_id' => $galaxy->id,
            'commodity_id' => $commodity->id,
            'min_qty_on_hand' => 5000,
            'npc_fallback_enabled' => true,
            'npc_price_multiplier' => 2.0,
        ]);

        $this->assertEquals(2.0, $policy->npc_price_multiplier);
    }

    public function test_reserve_policy_cascades_on_galaxy_delete(): void
    {
        $galaxy = Galaxy::factory()->create();
        $commodity = Commodity::factory()->create();

        ReservePolicy::create([
            'galaxy_id' => $galaxy->id,
            'commodity_id' => $commodity->id,
            'min_qty_on_hand' => 5000,
            'npc_fallback_enabled' => true,
            'npc_price_multiplier' => 1.5,
        ]);

        $galaxy->delete();

        $this->assertDatabaseMissing('reserve_policies', [
            'galaxy_id' => $galaxy->id,
        ]);
    }

    public function test_reserve_policy_nullifies_on_commodity_delete(): void
    {
        $galaxy = Galaxy::factory()->create();
        $commodity = Commodity::factory()->create();

        ReservePolicy::create([
            'galaxy_id' => $galaxy->id,
            'commodity_id' => $commodity->id,
            'min_qty_on_hand' => 5000,
            'npc_fallback_enabled' => true,
            'npc_price_multiplier' => 1.5,
        ]);

        $commodity->delete();

        $this->assertDatabaseHas('reserve_policies', [
            'galaxy_id' => $galaxy->id,
            'commodity_id' => null,
        ]);
    }

    public function test_reserve_policy_database_prevents_negative_min_qty(): void
    {
        $galaxy = Galaxy::factory()->create();
        $commodity = Commodity::factory()->create();

        // Unsigned decimal column should prevent negative values at DB level
        // This will either be rejected by the DB or cast to 0
        $policy = ReservePolicy::create([
            'galaxy_id' => $galaxy->id,
            'commodity_id' => $commodity->id,
            'min_qty_on_hand' => -5000,  // Negative value
            'npc_fallback_enabled' => true,
            'npc_price_multiplier' => 1.5,
        ]);

        // MySQL/SQLite may coerce negative to 0 or reject; verify it's not negative
        $this->assertGreaterThanOrEqual(0, $policy->fresh()->min_qty_on_hand);
    }

    public function test_reserve_policy_database_prevents_negative_price_multiplier(): void
    {
        $galaxy = Galaxy::factory()->create();
        $commodity = Commodity::factory()->create();

        // Unsigned decimal column should prevent negative values
        $policy = ReservePolicy::create([
            'galaxy_id' => $galaxy->id,
            'commodity_id' => $commodity->id,
            'min_qty_on_hand' => 5000,
            'npc_fallback_enabled' => true,
            'npc_price_multiplier' => -1.5,  // Negative multiplier
        ]);

        // Verify it's not negative after DB roundtrip
        $this->assertGreaterThanOrEqual(0, $policy->fresh()->npc_price_multiplier);
    }

    public function test_reserve_policy_accepts_zero_values(): void
    {
        $galaxy = Galaxy::factory()->create();
        $commodity = Commodity::factory()->create();

        $policy = ReservePolicy::create([
            'galaxy_id' => $galaxy->id,
            'commodity_id' => $commodity->id,
            'min_qty_on_hand' => 0,
            'npc_fallback_enabled' => true,
            'npc_price_multiplier' => 0,
        ]);

        $this->assertEquals(0, $policy->fresh()->min_qty_on_hand);
        $this->assertEquals(0, $policy->fresh()->npc_price_multiplier);
    }
}
