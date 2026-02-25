<?php

namespace Tests\Feature\Api;

use App\Models\Galaxy;
use App\Models\Player;
use App\Models\PlayerShip;
use App\Models\PointOfInterest;
use App\Models\Ship;
use App\Models\TradingHub;
use App\Models\TradingHubShip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShipShopTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Player $player;

    private Galaxy $galaxy;

    private TradingHub $tradingHub;

    private Ship $availableShip;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->galaxy = Galaxy::factory()->create();
        $poi = PointOfInterest::factory()->create(['galaxy_id' => $this->galaxy->id]);

        $this->player = Player::factory()->create([
            'user_id' => $this->user->id,
            'galaxy_id' => $this->galaxy->id,
            'current_poi_id' => $poi->id,
            'credits' => 50000,
        ]);

        // Create trading hub
        $this->tradingHub = TradingHub::factory()->create([
            'poi_id' => $poi->id,
        ]);

        // Create available ship
        $this->availableShip = Ship::factory()->create([
            'name' => 'Scout Frigate',
            'class' => 'frigate',
            'base_price' => 10000,
            'is_available' => true,
        ]);

        // Add ship to trading hub inventory
        TradingHubShip::create([
            'trading_hub_id' => $this->tradingHub->id,
            'ship_id' => $this->availableShip->id,
            'galaxy_id' => $this->galaxy->id,
            'quantity' => 5,
            'current_price' => 10000,
        ]);
    }

    public function test_it_gets_shipyard_with_available_ships()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/trading-hubs/{$this->tradingHub->uuid}/ship-shop");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'has_shipyard' => true,
                ],
            ])
            ->assertJsonStructure([
                'data' => [
                    'has_shipyard',
                    'trading_hub_name',
                    'available_ships' => [
                        '*' => [
                            'ship',
                            'current_price',
                            'quantity',
                        ],
                    ],
                ],
            ]);

        $this->assertCount(1, $response->json('data.available_ships'));
    }

    public function test_it_returns_false_when_hub_has_no_shipyard()
    {
        // Remove all ships from inventory to make it not a shipyard
        TradingHubShip::where('trading_hub_id', $this->tradingHub->id)->delete();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/trading-hubs/{$this->tradingHub->uuid}/ship-shop");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'has_shipyard' => false,
                    'available_ships' => [],
                ],
            ]);
    }

    public function test_it_gets_ship_catalog()
    {
        Ship::factory()->count(3)->create(['is_available' => true]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/ships/catalog');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'ships',
                    'total_count',
                ],
            ]);

        $this->assertGreaterThanOrEqual(4, $response->json('data.total_count'));
    }

    public function test_it_filters_ship_catalog_by_rarity()
    {
        Ship::factory()->create(['rarity' => 'common', 'is_available' => true]);
        Ship::factory()->create(['rarity' => 'rare', 'is_available' => true]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/ships/catalog?rarity=rare');

        $response->assertOk();

        // All returned ships should be rare
        foreach ($response->json('data.ships') as $ship) {
            $this->assertEquals('rare', $ship['rarity']);
        }
    }

    public function test_it_purchases_ship_successfully()
    {
        $oldCredits = $this->player->credits;

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/players/{$this->player->uuid}/ships/purchase", [
                'ship_id' => $this->availableShip->id,
                'trading_hub_uuid' => $this->tradingHub->uuid,
                'trade_in_current_ship' => false,
            ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'ship',
                    'cost_paid',
                    'net_cost',
                    'remaining_credits',
                ],
            ]);

        // Verify credits deducted
        $this->player->refresh();
        $this->assertEquals($oldCredits - 10000, $this->player->credits);

        // Verify player has new ship
        $this->assertDatabaseHas('player_ships', [
            'player_id' => $this->player->id,
            'ship_id' => $this->availableShip->id,
            'is_active' => true,
        ]);
    }

    public function test_it_purchases_ship_with_trade_in()
    {
        // Give player a current ship
        $currentShip = PlayerShip::factory()->create([
            'player_id' => $this->player->id,
            'is_active' => true,
            'hull' => 100,
            'max_hull' => 100,
        ]);

        $oldShipId = $currentShip->id;
        $oldCredits = $this->player->credits;

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/players/{$this->player->uuid}/ships/purchase", [
                'ship_id' => $this->availableShip->id,
                'trading_hub_uuid' => $this->tradingHub->uuid,
                'trade_in_current_ship' => true,
            ]);

        $response->assertOk();

        // Verify trade-in value was applied
        $tradeInValue = $response->json('data.trade_in_value');
        $this->assertGreaterThan(0, $tradeInValue);

        // Net cost should be less than full price
        $netCost = $response->json('data.net_cost');
        $this->assertLessThan(10000, $netCost);

        // Verify old ship was deleted
        $this->assertDatabaseMissing('player_ships', [
            'id' => $oldShipId,
        ]);

        // Verify new ship is active
        $this->assertDatabaseHas('player_ships', [
            'player_id' => $this->player->id,
            'ship_id' => $this->availableShip->id,
            'is_active' => true,
        ]);
    }

    public function test_it_fails_purchase_with_insufficient_credits()
    {
        $this->player->update(['credits' => 100]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/players/{$this->player->uuid}/ships/purchase", [
                'ship_id' => $this->availableShip->id,
                'trading_hub_uuid' => $this->tradingHub->uuid,
            ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'error' => [
                    'message' => 'Insufficient credits',
                ],
            ]);
    }

    public function test_it_fails_purchase_when_ship_not_in_inventory()
    {
        $unavailableShip = Ship::factory()->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/players/{$this->player->uuid}/ships/purchase", [
                'ship_id' => $unavailableShip->id,
                'trading_hub_uuid' => $this->tradingHub->uuid,
            ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'error' => [
                    'message' => 'This ship is not available at this trading hub',
                ],
            ]);
    }

    public function test_it_switches_active_ship()
    {
        // Create two ships for player
        $ship1 = PlayerShip::factory()->create([
            'player_id' => $this->player->id,
            'is_active' => true,
        ]);

        $ship2 = PlayerShip::factory()->create([
            'player_id' => $this->player->id,
            'is_active' => false,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/players/{$this->player->uuid}/ships/switch", [
                'ship_uuid' => $ship2->uuid,
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        // Verify ship2 is now active
        $ship2->refresh();
        $this->assertTrue($ship2->is_active);

        // Verify ship1 is deactivated
        $ship1->refresh();
        $this->assertFalse($ship1->is_active);
    }

    public function test_it_fails_to_switch_to_ship_not_owned()
    {
        $otherPlayer = Player::factory()->create([
            'galaxy_id' => $this->galaxy->id,
        ]);
        $otherShip = PlayerShip::factory()->create([
            'player_id' => $otherPlayer->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/players/{$this->player->uuid}/ships/switch", [
                'ship_uuid' => $otherShip->uuid,
            ]);

        $response->assertStatus(403);
    }

    public function test_it_gets_player_fleet()
    {
        // Create multiple ships for player
        PlayerShip::factory()->count(3)->create([
            'player_id' => $this->player->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/players/{$this->player->uuid}/ships/fleet");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'fleet',
                    'total_ships',
                    'active_ship_uuid',
                ],
            ]);

        $this->assertEquals(3, $response->json('data.total_ships'));
    }

    public function test_it_requires_authentication()
    {
        $response = $this->getJson("/api/trading-hubs/{$this->tradingHub->uuid}/ship-shop");

        $response->assertUnauthorized();
    }

    public function test_it_validates_required_fields_for_purchase()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/players/{$this->player->uuid}/ships/purchase", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['trading_hub_uuid']);
    }

    public function test_inventory_quantity_decreases_after_purchase()
    {
        $initialQuantity = TradingHubShip::where('trading_hub_id', $this->tradingHub->id)
            ->where('ship_id', $this->availableShip->id)
            ->first()
            ->quantity;

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/players/{$this->player->uuid}/ships/purchase", [
                'ship_id' => $this->availableShip->id,
                'trading_hub_uuid' => $this->tradingHub->uuid,
            ]);

        $newQuantity = TradingHubShip::where('trading_hub_id', $this->tradingHub->id)
            ->where('ship_id', $this->availableShip->id)
            ->first()
            ->quantity;

        $this->assertEquals($initialQuantity - 1, $newQuantity);
    }
}
