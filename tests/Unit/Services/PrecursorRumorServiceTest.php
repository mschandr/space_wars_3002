<?php

namespace Tests\Unit\Services;

use App\Models\Galaxy;
use App\Models\Player;
use App\Models\PointOfInterest;
use App\Models\PrecursorShip;
use App\Models\TradingHub;
use App\Models\User;
use App\Services\PrecursorRumorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrecursorRumorServiceTest extends TestCase
{
    use RefreshDatabase;

    private PrecursorRumorService $service;

    private Galaxy $galaxy;

    private PrecursorShip $precursorShip;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PrecursorRumorService::class);

        // Create test galaxy with Precursor ship
        $this->galaxy = Galaxy::factory()->create([
            'width' => 500,
            'height' => 500,
        ]);

        $this->precursorShip = PrecursorShip::create([
            'galaxy_id' => $this->galaxy->id,
            'x' => 250,
            'y' => 250,
            'is_discovered' => false,
            'hull' => 1000000,
            'max_hull' => 1000000,
            'weapons' => 10000,
            'sensors' => 100,
            'speed' => 10000,
            'warp_drive' => 100,
            'cargo_capacity' => 1000000,
            'current_cargo' => 0,
            'fuel' => 999999999,
            'max_fuel' => 999999999,
            'precursor_name' => 'Void Strider',
        ]);
    }

    public function test_generates_rumors_for_all_trading_hubs(): void
    {
        // Create trading hubs
        for ($i = 0; $i < 5; $i++) {
            $poi = PointOfInterest::factory()->create(['galaxy_id' => $this->galaxy->id]);
            TradingHub::factory()->create(['poi_id' => $poi->id]);
        }

        $count = $this->service->generateRumorsForGalaxy($this->galaxy);

        $this->assertEquals(5, $count);

        // Verify all hubs have rumors
        $hubsWithRumors = TradingHub::whereNotNull('precursor_rumor_x')
            ->whereHas('pointOfInterest', fn ($q) => $q->where('galaxy_id', $this->galaxy->id))
            ->count();

        $this->assertEquals(5, $hubsWithRumors);
    }

    public function test_rumored_locations_are_wrong(): void
    {
        $poi = PointOfInterest::factory()->create(['galaxy_id' => $this->galaxy->id]);
        $hub = TradingHub::factory()->create(['poi_id' => $poi->id]);

        $this->service->generateRumorForHub($hub, $this->precursorShip, $this->galaxy);
        $hub->refresh();

        // Verify rumored location is at least 50 units from real location
        $distance = sqrt(
            pow($hub->precursor_rumor_x - $this->precursorShip->x, 2) +
            pow($hub->precursor_rumor_y - $this->precursorShip->y, 2)
        );

        $this->assertGreaterThanOrEqual(50, $distance, 'Rumored location should be at least 50 units from real location');
    }

    public function test_bribe_requires_sufficient_credits(): void
    {
        $user = User::factory()->create();
        $poi = PointOfInterest::factory()->create(['galaxy_id' => $this->galaxy->id]);
        $hub = TradingHub::factory()->create(['poi_id' => $poi->id]);

        $player = Player::factory()->create([
            'user_id' => $user->id,
            'galaxy_id' => $this->galaxy->id,
            'current_poi_id' => $poi->id,
            'credits' => 100, // Very low credits
        ]);

        $this->service->generateRumorForHub($hub, $this->precursorShip, $this->galaxy);
        $hub->refresh();

        $result = $this->service->bribeForRumor($player, $hub);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('credits', $result['error']);
    }

    public function test_successful_bribe_returns_rumor(): void
    {
        $user = User::factory()->create();
        $poi = PointOfInterest::factory()->create(['galaxy_id' => $this->galaxy->id]);
        $hub = TradingHub::factory()->create(['poi_id' => $poi->id]);

        $player = Player::factory()->create([
            'user_id' => $user->id,
            'galaxy_id' => $this->galaxy->id,
            'current_poi_id' => $poi->id,
            'credits' => 1000000, // Plenty of credits
        ]);

        $this->service->generateRumorForHub($hub, $this->precursorShip, $this->galaxy);
        $hub->refresh();

        $initialCredits = $player->credits;
        $result = $this->service->bribeForRumor($player, $hub);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('rumor', $result);
        $this->assertEquals($hub->precursor_rumor_x, $result['rumor']['x']);
        $this->assertEquals($hub->precursor_rumor_y, $result['rumor']['y']);

        // Verify credits were deducted
        $player->refresh();
        $this->assertEquals($initialCredits - $hub->precursor_bribe_cost, $player->credits);
    }

    public function test_cannot_bribe_same_hub_twice(): void
    {
        $user = User::factory()->create();
        $poi = PointOfInterest::factory()->create(['galaxy_id' => $this->galaxy->id]);
        $hub = TradingHub::factory()->create(['poi_id' => $poi->id]);

        $player = Player::factory()->create([
            'user_id' => $user->id,
            'galaxy_id' => $this->galaxy->id,
            'current_poi_id' => $poi->id,
            'credits' => 1000000,
        ]);

        $this->service->generateRumorForHub($hub, $this->precursorShip, $this->galaxy);
        $hub->refresh();

        // First bribe succeeds
        $result1 = $this->service->bribeForRumor($player, $hub);
        $this->assertTrue($result1['success']);

        // Second bribe fails
        $result2 = $this->service->bribeForRumor($player, $hub);
        $this->assertFalse($result2['success']);
        $this->assertTrue($result2['already_obtained'] ?? false);
    }

    public function test_player_can_collect_multiple_rumors(): void
    {
        $user = User::factory()->create();
        $playerPoi = PointOfInterest::factory()->create(['galaxy_id' => $this->galaxy->id]);

        $player = Player::factory()->create([
            'user_id' => $user->id,
            'galaxy_id' => $this->galaxy->id,
            'current_poi_id' => $playerPoi->id,
            'credits' => 10000000,
        ]);

        // Create multiple hubs and get rumors from each
        for ($i = 0; $i < 3; $i++) {
            $poi = PointOfInterest::factory()->create(['galaxy_id' => $this->galaxy->id]);
            $hub = TradingHub::factory()->create(['poi_id' => $poi->id]);
            $this->service->generateRumorForHub($hub, $this->precursorShip, $this->galaxy);
            $hub->refresh();

            // Move player to this hub and bribe
            $player->current_poi_id = $poi->id;
            $player->save();

            // Reload player to get fresh relationship
            $player = $player->fresh();
            $player->load('currentLocation');

            $result = $this->service->bribeForRumor($player, $hub);
            $this->assertTrue($result['success'], "Bribe should succeed for hub {$i}");
        }

        // Get all collected rumors
        $rumors = $this->service->getPlayerRumors($player);

        $this->assertCount(3, $rumors);
    }

    public function test_gossip_is_free_but_gives_no_coordinates(): void
    {
        $poi = PointOfInterest::factory()->create(['galaxy_id' => $this->galaxy->id]);
        $hub = TradingHub::factory()->create(['poi_id' => $poi->id]);

        $this->service->generateRumorForHub($hub, $this->precursorShip, $this->galaxy);
        $hub->refresh();

        $gossip = $this->service->getShipyardGossip($hub);

        // Should mention the Precursor ship legend
        $this->assertStringContainsString('Precursor ship', $gossip);
        // Should mention the bribe cost
        $this->assertStringContainsString('credits', $gossip);
        // Should NOT contain actual coordinates
        $this->assertStringNotContainsString((string) $hub->precursor_rumor_x, $gossip);
    }

    public function test_rumor_accuracy_check(): void
    {
        // Create a rumor that's relatively close (but still wrong)
        $accuracy1 = $this->service->checkRumorAccuracy(260, 260, $this->galaxy);
        $this->assertEquals('burning', $accuracy1['accuracy']); // Within 30 units

        // Create a rumor that's further away
        $accuracy2 = $this->service->checkRumorAccuracy(300, 300, $this->galaxy);
        $this->assertContains($accuracy2['accuracy'], ['burning', 'warm', 'tepid']); // 50-70 units

        // Create a rumor that's very far
        $accuracy3 = $this->service->checkRumorAccuracy(450, 450, $this->galaxy);
        $this->assertEquals('cold', $accuracy3['accuracy']); // Over 200 units
    }
}
