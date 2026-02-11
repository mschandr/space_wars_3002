<?php

namespace Tests\Feature\Api;

use App\Models\Galaxy;
use App\Models\MarketEvent;
use App\Models\Mineral;
use App\Models\PointOfInterest;
use App\Models\TradingHub;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketEventTest extends TestCase
{
    use RefreshDatabase;

    private Galaxy $galaxy;

    private TradingHub $tradingHub;

    private Mineral $mineral;

    protected function setUp(): void
    {
        parent::setUp();

        $this->galaxy = Galaxy::factory()->create();
        $poi = PointOfInterest::factory()->create(['galaxy_id' => $this->galaxy->id]);
        $this->tradingHub = TradingHub::factory()->create([
            'poi_id' => $poi->id,
        ]);
        $this->mineral = Mineral::factory()->create();
    }

    public function test_it_can_list_galaxy_market_events()
    {
        // Create active events
        MarketEvent::factory()->count(3)->create([
            'mineral_id' => $this->mineral->id,
            'trading_hub_id' => $this->tradingHub->id,
            'is_active' => true,
        ]);

        // Create inactive event (shouldn't appear)
        MarketEvent::factory()->create([
            'mineral_id' => $this->mineral->id,
            'is_active' => false,
        ]);

        $response = $this->getJson("/api/galaxies/{$this->galaxy->uuid}/market-events");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.total_active_events', 3);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'galaxy' => ['uuid', 'name'],
                'total_active_events',
                'events' => [
                    '*' => [
                        'uuid',
                        'event_type',
                        'mineral' => ['name', 'symbol'],
                        'price_multiplier',
                        'trading_hub',
                        'description',
                        'created_at',
                        'expires_at',
                        'time_remaining_seconds',
                    ],
                ],
            ],
        ]);
    }

    public function test_it_can_filter_events_by_type()
    {
        MarketEvent::factory()->create([
            'mineral_id' => $this->mineral->id,
            'trading_hub_id' => $this->tradingHub->id,
            'event_type' => 'supply_shortage',
            'is_active' => true,
        ]);

        MarketEvent::factory()->create([
            'mineral_id' => $this->mineral->id,
            'trading_hub_id' => $this->tradingHub->id,
            'event_type' => 'market_flooding',
            'is_active' => true,
        ]);

        $response = $this->getJson("/api/galaxies/{$this->galaxy->uuid}/market-events?event_type=supply_shortage");

        $response->assertStatus(200);
        $response->assertJsonPath('data.total_active_events', 1);
        $response->assertJsonPath('data.events.0.event_type', 'supply_shortage');
    }

    public function test_it_can_filter_events_by_mineral()
    {
        $goldMineral = Mineral::factory()->create(['symbol' => 'Au', 'name' => 'Gold']);
        $silverMineral = Mineral::factory()->create(['symbol' => 'Ag', 'name' => 'Silver']);

        MarketEvent::factory()->create([
            'mineral_id' => $goldMineral->id,
            'trading_hub_id' => $this->tradingHub->id,
            'is_active' => true,
        ]);

        MarketEvent::factory()->create([
            'mineral_id' => $silverMineral->id,
            'trading_hub_id' => $this->tradingHub->id,
            'is_active' => true,
        ]);

        $response = $this->getJson("/api/galaxies/{$this->galaxy->uuid}/market-events?mineral=Au");

        $response->assertStatus(200);
        $response->assertJsonPath('data.total_active_events', 1);
        $response->assertJsonPath('data.events.0.mineral.symbol', 'Au');
    }

    public function test_it_can_get_market_event_details()
    {
        $event = MarketEvent::factory()->create([
            'mineral_id' => $this->mineral->id,
            'trading_hub_id' => $this->tradingHub->id,
            'event_type' => 'supply_shortage',
            'price_multiplier' => 1.5,
            'is_active' => true,
        ]);

        $response = $this->getJson("/api/market-events/{$event->uuid}");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.uuid', (string) $event->uuid);
        $response->assertJsonPath('data.event_type', 'supply_shortage');
        $response->assertJsonPath('data.price_multiplier', 1.5);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'uuid',
                'event_type',
                'galaxy' => ['uuid', 'name'],
                'mineral' => ['uuid', 'name', 'symbol', 'base_price'],
                'price_multiplier',
                'modified_price',
                'trading_hub',
                'description',
                'is_active',
                'created_at',
                'expires_at',
                'time_remaining_seconds',
            ],
        ]);
    }

    public function test_it_calculates_modified_price_correctly()
    {
        $this->mineral->update(['base_value' => 100]);

        $event = MarketEvent::factory()->create([
            'mineral_id' => $this->mineral->id,
            'price_multiplier' => 2.0, // Double price
            'is_active' => true,
        ]);

        $response = $this->getJson("/api/market-events/{$event->uuid}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.modified_price', 200);
    }

    public function test_it_can_get_trading_hub_events()
    {
        $user = User::factory()->create();

        MarketEvent::factory()->count(2)->create([
            'mineral_id' => $this->mineral->id,
            'trading_hub_id' => $this->tradingHub->id,
            'is_active' => true,
        ]);

        // Event at different hub (shouldn't appear)
        $otherPoi = PointOfInterest::factory()->create(['galaxy_id' => $this->galaxy->id]);
        $otherHub = TradingHub::factory()->create([
            'poi_id' => $otherPoi->id,
        ]);

        MarketEvent::factory()->create([
            'mineral_id' => $this->mineral->id,
            'trading_hub_id' => $otherHub->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/trading-hubs/{$this->tradingHub->uuid}/active-events");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.active_events_count', 2);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'trading_hub' => ['uuid', 'name', 'location'],
                'active_events_count',
                'events' => [
                    '*' => [
                        'uuid',
                        'event_type',
                        'mineral',
                        'price_multiplier',
                        'modified_price',
                        'price_change_percent',
                        'description',
                        'expires_at',
                        'time_remaining_seconds',
                    ],
                ],
            ],
        ]);
    }

    public function test_it_calculates_price_change_percent()
    {
        $user = User::factory()->create();

        $event = MarketEvent::factory()->create([
            'mineral_id' => $this->mineral->id,
            'trading_hub_id' => $this->tradingHub->id,
            'price_multiplier' => 1.25, // 25% increase
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/trading-hubs/{$this->tradingHub->uuid}/active-events");

        $response->assertStatus(200);
        $response->assertJsonPath('data.events.0.price_change_percent', 25);
    }

    public function test_it_returns_404_for_nonexistent_galaxy()
    {
        $response = $this->getJson('/api/galaxies/nonexistent-uuid/market-events');

        $response->assertStatus(404);
    }

    public function test_it_returns_404_for_nonexistent_event()
    {
        $response = $this->getJson('/api/market-events/nonexistent-uuid');

        $response->assertStatus(404);
    }

    public function test_it_returns_404_for_nonexistent_trading_hub()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/api/trading-hubs/nonexistent-uuid/active-events');

        $response->assertStatus(404);
    }
}
