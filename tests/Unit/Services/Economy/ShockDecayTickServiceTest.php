<?php

namespace Tests\Unit\Services\Economy;

use App\Enums\Economy\ActorType;
use App\Enums\Economy\ShockType;
use App\Models\Commodity;
use App\Models\EconomicShock;
use App\Models\Galaxy;
use App\Services\Economy\ShockDecayTickService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShockDecayTickServiceTest extends TestCase
{
    use RefreshDatabase;

    private ShockDecayTickService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ShockDecayTickService::class);
    }

    public function test_deactivate_fully_decayed_shock()
    {
        // Create test data
        $galaxy = Galaxy::factory()->create();
        $commodity = Commodity::factory()->create();

        // Create shock that started long ago (will be fully decayed)
        $shock = EconomicShock::factory()->create([
            'galaxy_id' => $galaxy->id,
            'commodity_id' => $commodity->id,
            'shock_type' => ShockType::BOOM,
            'magnitude' => 1.5,
            'decay_half_life_ticks' => 100,  // Decays by half every 100 seconds
            'starts_at' => now()->subSeconds(1000),  // Started 1000 seconds ago - should be decayed
            'is_active' => true,
        ]);

        // Process tick
        $results = $this->service->processTick();

        // Verify shock was deactivated
        $this->assertEquals(1, $results['checked']);
        $this->assertEquals(1, $results['deactivated']);
        $this->assertEmpty($results['errors']);

        // Verify shock is inactive
        $shock->refresh();
        $this->assertFalse($shock->is_active);
        $this->assertNotNull($shock->ends_at);
    }

    public function test_keep_active_shocks_with_high_magnitude()
    {
        // Create test data
        $galaxy = Galaxy::factory()->create();
        $commodity = Commodity::factory()->create();

        // Create shock that just started (very high magnitude remaining)
        $shock = EconomicShock::factory()->create([
            'galaxy_id' => $galaxy->id,
            'commodity_id' => $commodity->id,
            'shock_type' => ShockType::BLOCKADE,
            'magnitude' => 2.0,
            'decay_half_life_ticks' => 1000,  // Decays slowly
            'starts_at' => now()->subSeconds(10),  // Just started 10 seconds ago
            'is_active' => true,
        ]);

        // Process tick
        $results = $this->service->processTick();

        // Verify shock is still checked but not deactivated
        $this->assertEquals(1, $results['checked']);
        $this->assertEquals(0, $results['deactivated']);
        $this->assertEmpty($results['errors']);

        // Verify shock is still active
        $shock->refresh();
        $this->assertTrue($shock->is_active);
    }

    public function test_skip_inactive_shocks()
    {
        // Create test data
        $galaxy = Galaxy::factory()->create();
        $commodity = Commodity::factory()->create();

        // Create already-inactive shock
        EconomicShock::factory()->create([
            'galaxy_id' => $galaxy->id,
            'commodity_id' => $commodity->id,
            'shock_type' => ShockType::BOOM,
            'magnitude' => 1.0,
            'decay_half_life_ticks' => 100,
            'starts_at' => now()->subSeconds(500),
            'is_active' => false,  // Already inactive
        ]);

        // Process tick
        $results = $this->service->processTick();

        // Verify inactive shocks are ignored
        $this->assertEquals(0, $results['checked']);
        $this->assertEquals(0, $results['deactivated']);
    }

    public function test_process_tick_dry_run_no_writes()
    {
        // Create test data
        $galaxy = Galaxy::factory()->create();
        $commodity = Commodity::factory()->create();

        $shock = EconomicShock::factory()->create([
            'galaxy_id' => $galaxy->id,
            'commodity_id' => $commodity->id,
            'shock_type' => ShockType::BOOM,
            'magnitude' => 1.0,
            'decay_half_life_ticks' => 100,
            'starts_at' => now()->subSeconds(1000),  // Will be fully decayed
            'is_active' => true,
        ]);

        // Process tick in dry-run mode
        $results = $this->service->processTick(null, true);

        // Verify results show deactivation would happen
        $this->assertEquals(1, $results['checked']);
        $this->assertEquals(1, $results['deactivated']);

        // Verify shock was NOT actually deactivated
        $shock->refresh();
        $this->assertTrue($shock->is_active);
        $this->assertNull($shock->ends_at);
    }

    public function test_galaxy_filter()
    {
        // Create two galaxies with shocks
        $galaxy1 = Galaxy::factory()->create();
        $galaxy2 = Galaxy::factory()->create();
        $commodity = Commodity::factory()->create();

        // Decayed shock in galaxy 1
        EconomicShock::factory()->create([
            'galaxy_id' => $galaxy1->id,
            'commodity_id' => $commodity->id,
            'shock_type' => ShockType::BOOM,
            'magnitude' => 1.0,
            'decay_half_life_ticks' => 100,
            'starts_at' => now()->subSeconds(1000),
            'is_active' => true,
        ]);

        // Decayed shock in galaxy 2
        EconomicShock::factory()->create([
            'galaxy_id' => $galaxy2->id,
            'commodity_id' => $commodity->id,
            'shock_type' => ShockType::BLOCKADE,
            'magnitude' => 1.0,
            'decay_half_life_ticks' => 100,
            'starts_at' => now()->subSeconds(1000),
            'is_active' => true,
        ]);

        // Process tick for only galaxy1
        $results = $this->service->processTick($galaxy1);

        // Verify only galaxy1 shock was processed
        $this->assertEquals(1, $results['checked']);
        $this->assertEquals(1, $results['deactivated']);

        // Verify galaxy2 shock is still active
        $galaxy2Shock = EconomicShock::where('galaxy_id', $galaxy2->id)->first();
        $this->assertTrue($galaxy2Shock->is_active);
    }

    public function test_multiple_shocks_mixed_decay_states()
    {
        // Create test data
        $galaxy = Galaxy::factory()->create();
        $commodity = Commodity::factory()->create();

        // Decayed shock
        $decayedShock = EconomicShock::factory()->create([
            'galaxy_id' => $galaxy->id,
            'commodity_id' => $commodity->id,
            'shock_type' => ShockType::BOOM,
            'magnitude' => 1.0,
            'decay_half_life_ticks' => 100,
            'starts_at' => now()->subSeconds(1000),
            'is_active' => true,
        ]);

        // Active shock
        $activeShock = EconomicShock::factory()->create([
            'galaxy_id' => $galaxy->id,
            'commodity_id' => $commodity->id,
            'shock_type' => ShockType::BOOM,
            'magnitude' => 2.0,
            'decay_half_life_ticks' => 1000,
            'starts_at' => now()->subSeconds(10),
            'is_active' => true,
        ]);

        // Process tick
        $results = $this->service->processTick();

        // Verify both were checked, but only one deactivated
        $this->assertEquals(2, $results['checked']);
        $this->assertEquals(1, $results['deactivated']);

        // Verify states
        $decayedShock->refresh();
        $this->assertFalse($decayedShock->is_active);

        $activeShock->refresh();
        $this->assertTrue($activeShock->is_active);
    }

    public function test_effective_magnitude_below_threshold()
    {
        // Create test data
        $galaxy = Galaxy::factory()->create();
        $commodity = Commodity::factory()->create();

        // Shock with magnitude just below threshold (< 1% of original)
        $shock = EconomicShock::factory()->create([
            'galaxy_id' => $galaxy->id,
            'commodity_id' => $commodity->id,
            'shock_type' => ShockType::BOOM,
            'magnitude' => 1.0,
            'decay_half_life_ticks' => 10,  // Very fast decay
            'starts_at' => now()->subSeconds(100),  // 100 seconds old, 10 half-lives
            'is_active' => true,
        ]);

        // Process tick
        $results = $this->service->processTick();

        // Verify shock was deactivated
        $this->assertEquals(1, $results['deactivated']);

        $shock->refresh();
        $this->assertFalse($shock->is_active);
    }

    public function test_zero_magnitude_shock_deactivates()
    {
        // Create test data
        $galaxy = Galaxy::factory()->create();
        $commodity = Commodity::factory()->create();

        // Shock with magnitude 0 (already decayed to nothing)
        $shock = EconomicShock::factory()->create([
            'galaxy_id' => $galaxy->id,
            'commodity_id' => $commodity->id,
            'shock_type' => ShockType::BOOM,
            'magnitude' => 0.0,
            'decay_half_life_ticks' => 100,
            'starts_at' => now(),
            'is_active' => true,
        ]);

        // Process tick
        $results = $this->service->processTick();

        // Verify shock was deactivated (magnitude < 0.01)
        $this->assertEquals(1, $results['deactivated']);

        $shock->refresh();
        $this->assertFalse($shock->is_active);
    }

    public function test_sets_ends_at_on_deactivation()
    {
        // Create test data
        $galaxy = Galaxy::factory()->create();
        $commodity = Commodity::factory()->create();

        $shock = EconomicShock::factory()->create([
            'galaxy_id' => $galaxy->id,
            'commodity_id' => $commodity->id,
            'shock_type' => ShockType::BOOM,
            'magnitude' => 1.0,
            'decay_half_life_ticks' => 100,
            'starts_at' => now()->subSeconds(1000),
            'ends_at' => null,  // Not set yet
            'is_active' => true,
        ]);

        // Process tick
        $this->service->processTick();

        // Verify ends_at is set
        $shock->refresh();
        $this->assertNotNull($shock->ends_at);
        $this->assertTrue($shock->ends_at->diffInSeconds(now()) < 5); // Within 5 seconds
    }
}
