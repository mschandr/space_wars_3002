<?php

namespace Tests\Unit\Services;

use App\Enums\MarketEventType;
use App\Models\MarketEvent;
use App\Models\Mineral;
use App\Models\TradingHub;
use App\Services\MarketEventService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketEventServiceTest extends TestCase
{
    use RefreshDatabase;

    private MarketEventService $marketEventService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->marketEventService = new MarketEventService;
    }

    /** @test */
    public function test_it_returns_no_multiplier_when_no_active_events()
    {
        $multiplier = $this->marketEventService->getCombinedMultiplier(null, null);

        $this->assertEquals(1.0, $multiplier);
    }

    /** @test */
    public function test_it_applies_single_event_multiplier_correctly()
    {
        $mineral = Mineral::factory()->create();
        $hub = TradingHub::factory()->create();

        MarketEvent::create([
            'mineral_id' => $mineral->id,
            'trading_hub_id' => $hub->id,
            'event_type' => MarketEventType::SUPPLY_SHORTAGE,
            'price_multiplier' => 2.5,
            'description' => 'Test shortage',
            'started_at' => now()->subHour(),
            'expires_at' => now()->addHour(),
            'is_active' => true,
        ]);

        $multiplier = $this->marketEventService->getCombinedMultiplier($mineral->id, $hub->id);

        $this->assertEquals(2.5, $multiplier);
    }

    /** @test */
    public function test_it_stacks_multiple_events_multiplicatively()
    {
        $mineral = Mineral::factory()->create();
        $hub = TradingHub::factory()->create();

        // First event: 2x multiplier
        MarketEvent::create([
            'mineral_id' => $mineral->id,
            'trading_hub_id' => $hub->id,
            'event_type' => MarketEventType::SUPPLY_SHORTAGE,
            'price_multiplier' => 2.0,
            'description' => 'Test shortage',
            'started_at' => now()->subHour(),
            'expires_at' => now()->addHour(),
            'is_active' => true,
        ]);

        // Second event: 1.5x multiplier
        MarketEvent::create([
            'mineral_id' => $mineral->id,
            'trading_hub_id' => $hub->id,
            'event_type' => MarketEventType::DEMAND_SPIKE,
            'price_multiplier' => 1.5,
            'description' => 'Test demand',
            'started_at' => now()->subHour(),
            'expires_at' => now()->addHour(),
            'is_active' => true,
        ]);

        $multiplier = $this->marketEventService->getCombinedMultiplier($mineral->id, $hub->id);

        // 2.0 * 1.5 = 3.0
        $this->assertEquals(3.0, $multiplier);
    }

    /** @test */
    public function test_it_applies_multiplier_to_base_price()
    {
        $mineral = Mineral::factory()->create();
        $hub = TradingHub::factory()->create();

        MarketEvent::create([
            'mineral_id' => $mineral->id,
            'trading_hub_id' => $hub->id,
            'event_type' => MarketEventType::SUPPLY_SHORTAGE,
            'price_multiplier' => 2.0,
            'description' => 'Test shortage',
            'started_at' => now()->subHour(),
            'expires_at' => now()->addHour(),
            'is_active' => true,
        ]);

        $basePrice = 100.00;
        $adjustedPrice = $this->marketEventService->applyEventMultiplier($basePrice, $mineral->id, $hub->id);

        $this->assertEquals(200.00, $adjustedPrice);
    }

    /** @test */
    public function test_it_ignores_expired_events()
    {
        $mineral = Mineral::factory()->create();
        $hub = TradingHub::factory()->create();

        MarketEvent::create([
            'mineral_id' => $mineral->id,
            'trading_hub_id' => $hub->id,
            'event_type' => MarketEventType::SUPPLY_SHORTAGE,
            'price_multiplier' => 2.5,
            'description' => 'Expired event',
            'started_at' => now()->subHours(3),
            'expires_at' => now()->subHour(),  // Expired 1 hour ago
            'is_active' => true,
        ]);

        $multiplier = $this->marketEventService->getCombinedMultiplier($mineral->id, $hub->id);

        $this->assertEquals(1.0, $multiplier); // No multiplier from expired event
    }

    /** @test */
    public function test_it_ignores_inactive_events()
    {
        $mineral = Mineral::factory()->create();
        $hub = TradingHub::factory()->create();

        MarketEvent::create([
            'mineral_id' => $mineral->id,
            'trading_hub_id' => $hub->id,
            'event_type' => MarketEventType::SUPPLY_SHORTAGE,
            'price_multiplier' => 2.5,
            'description' => 'Inactive event',
            'started_at' => now()->subHour(),
            'expires_at' => now()->addHour(),
            'is_active' => false,  // Not active
        ]);

        $multiplier = $this->marketEventService->getCombinedMultiplier($mineral->id, $hub->id);

        $this->assertEquals(1.0, $multiplier); // No multiplier from inactive event
    }

    /** @test */
    public function test_it_ignores_future_events()
    {
        $mineral = Mineral::factory()->create();
        $hub = TradingHub::factory()->create();

        MarketEvent::create([
            'mineral_id' => $mineral->id,
            'trading_hub_id' => $hub->id,
            'event_type' => MarketEventType::SUPPLY_SHORTAGE,
            'price_multiplier' => 2.5,
            'description' => 'Future event',
            'started_at' => now()->addHour(),  // Starts in the future
            'expires_at' => now()->addHours(3),
            'is_active' => true,
        ]);

        $multiplier = $this->marketEventService->getCombinedMultiplier($mineral->id, $hub->id);

        $this->assertEquals(1.0, $multiplier); // No multiplier from future event
    }

    /** @test */
    public function test_global_events_affect_all_minerals()
    {
        $mineral1 = Mineral::factory()->create();
        $mineral2 = Mineral::factory()->create();
        $hub = TradingHub::factory()->create();

        // Global event (null mineral_id)
        MarketEvent::create([
            'mineral_id' => null,  // Affects all minerals
            'trading_hub_id' => $hub->id,
            'event_type' => MarketEventType::MARKET_FLOODING,
            'price_multiplier' => 0.5,
            'description' => 'Global flooding',
            'started_at' => now()->subHour(),
            'expires_at' => now()->addHour(),
            'is_active' => true,
        ]);

        $multiplier1 = $this->marketEventService->getCombinedMultiplier($mineral1->id, $hub->id);
        $multiplier2 = $this->marketEventService->getCombinedMultiplier($mineral2->id, $hub->id);

        $this->assertEquals(0.5, $multiplier1);
        $this->assertEquals(0.5, $multiplier2);
    }

    /** @test */
    public function test_galaxy_wide_events_affect_all_hubs()
    {
        $mineral = Mineral::factory()->create();
        $hub1 = TradingHub::factory()->create();
        $hub2 = TradingHub::factory()->create();

        // Galaxy-wide event (null trading_hub_id)
        MarketEvent::create([
            'mineral_id' => $mineral->id,
            'trading_hub_id' => null,  // Affects all hubs
            'event_type' => MarketEventType::DEMAND_SPIKE,
            'price_multiplier' => 2.0,
            'description' => 'Galaxy-wide demand',
            'started_at' => now()->subHour(),
            'expires_at' => now()->addHour(),
            'is_active' => true,
        ]);

        $multiplier1 = $this->marketEventService->getCombinedMultiplier($mineral->id, $hub1->id);
        $multiplier2 = $this->marketEventService->getCombinedMultiplier($mineral->id, $hub2->id);

        $this->assertEquals(2.0, $multiplier1);
        $this->assertEquals(2.0, $multiplier2);
    }

    /** @test */
    public function test_specific_events_only_affect_specified_mineral()
    {
        $mineral1 = Mineral::factory()->create();
        $mineral2 = Mineral::factory()->create();
        $hub = TradingHub::factory()->create();

        // Event specific to mineral1
        MarketEvent::create([
            'mineral_id' => $mineral1->id,
            'trading_hub_id' => $hub->id,
            'event_type' => MarketEventType::SUPPLY_SHORTAGE,
            'price_multiplier' => 2.5,
            'description' => 'Mineral1 shortage',
            'started_at' => now()->subHour(),
            'expires_at' => now()->addHour(),
            'is_active' => true,
        ]);

        $multiplier1 = $this->marketEventService->getCombinedMultiplier($mineral1->id, $hub->id);
        $multiplier2 = $this->marketEventService->getCombinedMultiplier($mineral2->id, $hub->id);

        $this->assertEquals(2.5, $multiplier1); // Affected
        $this->assertEquals(1.0, $multiplier2);  // Not affected
    }

    /** @test */
    public function test_specific_events_only_affect_specified_hub()
    {
        $mineral = Mineral::factory()->create();
        $hub1 = TradingHub::factory()->create();
        $hub2 = TradingHub::factory()->create();

        // Event specific to hub1
        MarketEvent::create([
            'mineral_id' => $mineral->id,
            'trading_hub_id' => $hub1->id,
            'event_type' => MarketEventType::PIRATE_RAID,
            'price_multiplier' => 2.0,
            'description' => 'Hub1 raided',
            'started_at' => now()->subHour(),
            'expires_at' => now()->addHour(),
            'is_active' => true,
        ]);

        $multiplier1 = $this->marketEventService->getCombinedMultiplier($mineral->id, $hub1->id);
        $multiplier2 = $this->marketEventService->getCombinedMultiplier($mineral->id, $hub2->id);

        $this->assertEquals(2.0, $multiplier1); // Affected
        $this->assertEquals(1.0, $multiplier2);  // Not affected
    }

    /** @test */
    public function test_it_deactivates_expired_events()
    {
        // Create expired events
        MarketEvent::create([
            'mineral_id' => null,
            'trading_hub_id' => null,
            'event_type' => MarketEventType::SUPPLY_SHORTAGE,
            'price_multiplier' => 2.0,
            'description' => 'Expired 1',
            'started_at' => now()->subHours(3),
            'expires_at' => now()->subHour(),
            'is_active' => true,
        ]);

        MarketEvent::create([
            'mineral_id' => null,
            'trading_hub_id' => null,
            'event_type' => MarketEventType::DEMAND_SPIKE,
            'price_multiplier' => 1.5,
            'description' => 'Expired 2',
            'started_at' => now()->subHours(2),
            'expires_at' => now()->subMinutes(30),
            'is_active' => true,
        ]);

        // Create active event
        MarketEvent::create([
            'mineral_id' => null,
            'trading_hub_id' => null,
            'event_type' => MarketEventType::MARKET_FLOODING,
            'price_multiplier' => 0.5,
            'description' => 'Active',
            'started_at' => now()->subHour(),
            'expires_at' => now()->addHour(),
            'is_active' => true,
        ]);

        $deactivatedCount = $this->marketEventService->deactivateExpiredEvents();

        $this->assertEquals(2, $deactivatedCount);
        $this->assertEquals(1, MarketEvent::where('is_active', true)->count());
    }

    /** @test */
    public function test_it_checks_if_events_are_active()
    {
        $mineral = Mineral::factory()->create();
        $hub = TradingHub::factory()->create();

        $this->assertFalse($this->marketEventService->hasActiveEvents($mineral->id, $hub->id));

        MarketEvent::create([
            'mineral_id' => $mineral->id,
            'trading_hub_id' => $hub->id,
            'event_type' => MarketEventType::SUPPLY_SHORTAGE,
            'price_multiplier' => 2.0,
            'description' => 'Test event',
            'started_at' => now()->subHour(),
            'expires_at' => now()->addHour(),
            'is_active' => true,
        ]);

        $this->assertTrue($this->marketEventService->hasActiveEvents($mineral->id, $hub->id));
    }

    /** @test */
    public function test_it_gets_all_active_events_for_a_mineral_and_hub()
    {
        $mineral = Mineral::factory()->create();
        $hub = TradingHub::factory()->create();

        // Relevant events
        MarketEvent::create([
            'mineral_id' => $mineral->id,
            'trading_hub_id' => $hub->id,
            'event_type' => MarketEventType::SUPPLY_SHORTAGE,
            'price_multiplier' => 2.0,
            'description' => 'Specific event',
            'started_at' => now()->subHour(),
            'expires_at' => now()->addHour(),
            'is_active' => true,
        ]);

        MarketEvent::create([
            'mineral_id' => null,  // Global
            'trading_hub_id' => $hub->id,
            'event_type' => MarketEventType::DEMAND_SPIKE,
            'price_multiplier' => 1.5,
            'description' => 'Global mineral event',
            'started_at' => now()->subHour(),
            'expires_at' => now()->addHour(),
            'is_active' => true,
        ]);

        // Irrelevant event (different hub)
        $otherHub = TradingHub::factory()->create();
        MarketEvent::create([
            'mineral_id' => $mineral->id,
            'trading_hub_id' => $otherHub->id,
            'event_type' => MarketEventType::PIRATE_RAID,
            'price_multiplier' => 2.5,
            'description' => 'Other hub event',
            'started_at' => now()->subHour(),
            'expires_at' => now()->addHour(),
            'is_active' => true,
        ]);

        $events = $this->marketEventService->getActiveEvents($mineral->id, $hub->id);

        $this->assertEquals(2, $events->count());
    }

    /** @test */
    public function test_price_decrease_events_reduce_prices()
    {
        $mineral = Mineral::factory()->create();
        $hub = TradingHub::factory()->create();

        MarketEvent::create([
            'mineral_id' => $mineral->id,
            'trading_hub_id' => $hub->id,
            'event_type' => MarketEventType::MARKET_FLOODING,
            'price_multiplier' => 0.4,  // 40% of original price
            'description' => 'Market crash',
            'started_at' => now()->subHour(),
            'expires_at' => now()->addHour(),
            'is_active' => true,
        ]);

        $basePrice = 100.00;
        $adjustedPrice = $this->marketEventService->applyEventMultiplier($basePrice, $mineral->id, $hub->id);

        $this->assertEquals(40.00, $adjustedPrice);
    }

    /** @test */
    public function test_combining_increase_and_decrease_events()
    {
        $mineral = Mineral::factory()->create();
        $hub = TradingHub::factory()->create();

        // Price increase event (2x)
        MarketEvent::create([
            'mineral_id' => $mineral->id,
            'trading_hub_id' => $hub->id,
            'event_type' => MarketEventType::SUPPLY_SHORTAGE,
            'price_multiplier' => 2.0,
            'description' => 'Shortage',
            'started_at' => now()->subHour(),
            'expires_at' => now()->addHour(),
            'is_active' => true,
        ]);

        // Price decrease event (0.5x)
        MarketEvent::create([
            'mineral_id' => $mineral->id,
            'trading_hub_id' => $hub->id,
            'event_type' => MarketEventType::MARKET_FLOODING,
            'price_multiplier' => 0.5,
            'description' => 'Flooding',
            'started_at' => now()->subHour(),
            'expires_at' => now()->addHour(),
            'is_active' => true,
        ]);

        $multiplier = $this->marketEventService->getCombinedMultiplier($mineral->id, $hub->id);

        // 2.0 * 0.5 = 1.0 (they cancel out)
        $this->assertEquals(1.0, $multiplier);
    }

    /** @test */
    public function test_market_event_model_checks_if_currently_active()
    {
        $event = MarketEvent::create([
            'mineral_id' => null,
            'trading_hub_id' => null,
            'event_type' => MarketEventType::SUPPLY_SHORTAGE,
            'price_multiplier' => 2.0,
            'description' => 'Test',
            'started_at' => now()->subHour(),
            'expires_at' => now()->addHour(),
            'is_active' => true,
        ]);

        $this->assertTrue($event->isCurrentlyActive());
    }

    /** @test */
    public function test_market_event_model_detects_expiration()
    {
        $event = MarketEvent::create([
            'mineral_id' => null,
            'trading_hub_id' => null,
            'event_type' => MarketEventType::SUPPLY_SHORTAGE,
            'price_multiplier' => 2.0,
            'description' => 'Test',
            'started_at' => now()->subHours(3),
            'expires_at' => now()->subHour(),
            'is_active' => true,
        ]);

        $this->assertTrue($event->hasExpired());
        $this->assertFalse($event->isCurrentlyActive());
    }

    /** @test */
    public function test_market_event_can_be_deactivated()
    {
        $event = MarketEvent::create([
            'mineral_id' => null,
            'trading_hub_id' => null,
            'event_type' => MarketEventType::SUPPLY_SHORTAGE,
            'price_multiplier' => 2.0,
            'description' => 'Test',
            'started_at' => now()->subHour(),
            'expires_at' => now()->addHour(),
            'is_active' => true,
        ]);

        $this->assertTrue($event->is_active);

        $event->deactivate();

        $this->assertFalse($event->fresh()->is_active);
    }
}
